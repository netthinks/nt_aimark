<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Service;

use NetThinks\NtAimark\Domain\AiActCutoff;
use NetThinks\NtAimark\Domain\DigitalSourceType;
use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\C2paState;
use NetThinks\NtAimark\Domain\Enum\ExemptReason;
use NetThinks\NtAimark\Domain\Model\AiDeclaration;
use NetThinks\NtAimark\Domain\Model\ProvenanceResult;
use NetThinks\NtAimark\Service\AuditService;
use NetThinks\NtAimark\Service\C2paService;
use NetThinks\NtAimark\Service\ExifSignatureService;
use NetThinks\NtAimark\Service\ProvenanceExtractorService;
use NetThinks\NtAimark\Service\XmpReaderService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProvenanceExtractorServiceTest extends UnitTestCase
{
    private string $directory = '';

    private ProvenanceExtractorService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/nt-aimark-prov-' . uniqid('', true) . '/';
        mkdir($this->directory, 0o777, true);

        $this->subject = new ProvenanceExtractorService(
            // An empty binary path keeps the C2PA stage from shelling out, so
            // the ordering below is checked without depending on the tool.
            new C2paService(''),
            new XmpReaderService(),
            new ExifSignatureService(),
            new AuditService($this->createStub(ConnectionPool::class)),
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    private function fileWith(string $xmpBody = '', string $software = ''): string
    {
        $content = "\xFF\xD8\xFF\xE0";

        if ($software !== '') {
            $content .= 'Exif junk Software: ' . $software;
        }

        if ($xmpBody !== '') {
            $content .= '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
                . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
                . '<rdf:Description xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/">'
                . $xmpBody
                . '</rdf:Description></rdf:RDF></x:xmpmeta>';
        }

        $path = $this->directory . 'file-' . uniqid('', true) . '.jpg';
        file_put_contents($path, $content . "\xFF\xD9");

        return $path;
    }

    #[Test]
    public function xmpIsUsedWhenThereIsNoSignedManifest(): void
    {
        $path = $this->fileWith(
            '<Iptc4xmpExt:DigitalSourceType>' . DigitalSourceType::TRAINED_ALGORITHMIC_MEDIA . '</Iptc4xmpExt:DigitalSourceType>',
        );

        $result = $this->subject->extract($path);

        self::assertSame('xmp', $result->detectedBy);
        self::assertSame(AiStatus::Generated, $result->suggestedStatus);
    }

    #[Test]
    public function aFileWithNothingToFindProducesNoSuggestion(): void
    {
        $result = $this->subject->extract($this->fileWith());

        self::assertFalse($result->hasFinding());
    }

    /**
     * The rule that keeps a machine guess from overruling a person.
     *
     * @return array<string, array{AiStatus, bool}>
     */
    public static function statusesOpenToDetection(): array
    {
        return [
            'never looked at' => [AiStatus::Unreviewed, true],
            'an earlier suggestion may be refreshed' => [AiStatus::Suggested, true],
            'a confirmed "no AI" is final' => [AiStatus::NoAi, false],
            'a confirmed "generated" is final' => [AiStatus::Generated, false],
            'a confirmed "modified" is final' => [AiStatus::Modified, false],
            'a confirmed "unknown origin" is final' => [AiStatus::UnknownOrigin, false],
        ];
    }

    #[Test]
    #[DataProvider('statusesOpenToDetection')]
    public function detectionNeverOverwritesAHumanDecision(AiStatus $status, bool $expected): void
    {
        $declaration = new AiDeclaration(tableName: 'sys_file_metadata', recordUid: 1, status: $status);

        self::assertSame($expected, $this->subject->isOpenForSuggestion($declaration));
    }

    /**
     * However confident a stage is, what lands in the record is a suggestion.
     *
     * @return array<string, array{AiStatus}>
     */
    public static function confidentFindings(): array
    {
        return [
            'generated' => [AiStatus::Generated],
            'modified' => [AiStatus::Modified],
        ];
    }

    #[Test]
    #[DataProvider('confidentFindings')]
    public function aFindingIsWrittenBackAsASuggestionAndNothingStronger(AiStatus $found): void
    {
        $changes = $this->subject->changeSet(new ProvenanceResult(suggestedStatus: $found));

        self::assertSame(AiStatus::Suggested->value, $changes['tx_ntaimark_status']);
    }

    #[Test]
    public function contentFromBeforeTheCutoffGetsTheExemptionProposed(): void
    {
        $changes = $this->subject->changeSet(new ProvenanceResult(
            suggestedStatus: AiStatus::Generated,
            createdAt: AiActCutoff::TIMESTAMP - 86400,
        ));

        self::assertSame(ExemptReason::PreCutoff->value, $changes['tx_ntaimark_exempt_reason']);
    }

    #[Test]
    public function contentFromAfterTheCutoffGetsNoExemption(): void
    {
        $changes = $this->subject->changeSet(new ProvenanceResult(
            suggestedStatus: AiStatus::Generated,
            createdAt: AiActCutoff::TIMESTAMP + 86400,
        ));

        self::assertArrayNotHasKey('tx_ntaimark_exempt_reason', $changes);
    }

    /**
     * Without a finding there is no status to write — but a C2PA state or a
     * creation date is still worth keeping.
     */
    #[Test]
    public function aResultWithoutAFindingWritesNoStatus(): void
    {
        $changes = $this->subject->changeSet(new ProvenanceResult(c2paState: C2paState::Broken));

        self::assertArrayNotHasKey('tx_ntaimark_status', $changes);
        self::assertSame(C2paState::Broken->value, $changes['tx_ntaimark_c2pa_state']);
    }

    #[Test]
    public function anEmptyResultWritesNothingAtAll(): void
    {
        self::assertSame([], $this->subject->changeSet(ProvenanceResult::nothing()));
    }
}
