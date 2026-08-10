<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Service;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\C2paState;
use NetThinks\NtAimark\Service\C2paService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Works against output captured from c2patool 0.27.7 rather than a hand-made
 * example, so the mapping is checked against what the tool really emits.
 */
final class C2paServiceTest extends UnitTestCase
{
    private const FIXTURES = __DIR__ . '/../../Fixtures/C2pa/';

    private C2paService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new C2paService();
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(self::FIXTURES . $name . '.json');
    }

    #[Test]
    public function anIntactManifestCountsAsValid(): void
    {
        $result = $this->subject->interpretReport($this->fixture('valid'), 0, '');

        self::assertSame(C2paState::Valid, $result->c2paState);
    }

    /**
     * The one that must not be got wrong: c2patool exits 0 for a tampered
     * file. Trusting the exit code would report a broken signature as intact.
     */
    #[Test]
    public function aTamperedFileIsRecognisedDespiteASuccessfulExitCode(): void
    {
        $result = $this->subject->interpretReport($this->fixture('tampered'), 0, '');

        self::assertSame(C2paState::Broken, $result->c2paState);
    }

    /**
     * The captured fixture is signed with a test certificate that is in no
     * trust list. The manifest is still intact, and calling it broken would
     * accuse an untampered file of being tampered with.
     */
    #[Test]
    public function anUntrustedSignerAloneDoesNotMakeAManifestBroken(): void
    {
        self::assertStringContainsString('signingCredential.untrusted', $this->fixture('valid'));
        self::assertSame(C2paState::Valid, $this->subject->interpretReport($this->fixture('valid'), 0, '')->c2paState);
    }

    /**
     * @return array<string, array{int, string, C2paState}>
     */
    public static function failureModes(): array
    {
        return [
            'no manifest in the file' => [1, 'Error: No claim found', C2paState::None],
            'file cannot be opened' => [1, 'Error: No such file or directory (os error 2)', C2paState::NotVerifiable],
            'format not supported' => [1, 'Error: Unsupported file type', C2paState::NotVerifiable],
            'binary missing' => [-1, '', C2paState::NotVerifiable],
        ];
    }

    #[Test]
    #[DataProvider('failureModes')]
    public function eachFailureModeMapsToItsOwnState(int $exitCode, string $stderr, C2paState $expected): void
    {
        $result = $this->subject->interpretReport('', $exitCode, $stderr);

        self::assertSame($expected, $result->c2paState);
        self::assertFalse($result->hasFinding());
    }

    #[Test]
    public function unreadableOutputIsNotVerifiableRatherThanAnError(): void
    {
        $result = $this->subject->interpretReport('this is not json', 0, '');

        self::assertSame(C2paState::NotVerifiable, $result->c2paState);
    }

    #[Test]
    public function theStoredManifestIsCappedAtTheDocumentedLimit(): void
    {
        $oversized = '{"validation_state":"Valid","padding":"' . str_repeat('x', 100_000) . '"}';

        $result = $this->subject->interpretReport($oversized, 0, '');

        self::assertLessThanOrEqual(C2paService::MANIFEST_LIMIT, strlen($result->c2paManifest));
    }

    #[Test]
    public function theProducingApplicationIsTakenFromTheManifest(): void
    {
        $result = $this->subject->interpretReport($this->fixture('valid'), 0, '');

        self::assertNotSame('', $result->system);
        // Library noise from claim_generator must not end up in the field.
        self::assertStringNotContainsString('c2pa-rs', $result->system);
        self::assertStringNotContainsString('/', $result->system);
    }

    /**
     * @return array<string, array{string, ?AiStatus}>
     */
    public static function sourceTypes(): array
    {
        return [
            'trained algorithmic media' => [
                'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia',
                AiStatus::Generated,
            ],
            'composite with trained algorithmic media' => [
                'http://cv.iptc.org/newscodes/digitalsourcetype/compositeWithTrainedAlgorithmicMedia',
                AiStatus::Modified,
            ],
            'algorithmic media' => [
                'http://cv.iptc.org/newscodes/digitalsourcetype/algorithmicMedia',
                AiStatus::Generated,
            ],
            'a plain photograph suggests nothing' => [
                'http://cv.iptc.org/newscodes/digitalsourcetype/digitalCapture',
                null,
            ],
        ];
    }

    #[Test]
    #[DataProvider('sourceTypes')]
    public function theDigitalSourceTypeDrivesTheSuggestion(string $uri, ?AiStatus $expected): void
    {
        $report = json_encode([
            'validation_state' => 'Valid',
            'active_manifest' => 'urn:test',
            'manifests' => [
                'urn:test' => [
                    'claim_generator' => 'SomeTool/1.0',
                    'assertions' => [
                        ['label' => 'c2pa.actions.v2', 'data' => ['actions' => [
                            ['action' => 'c2pa.created', 'digitalSourceType' => $uri],
                        ]]],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $this->subject->interpretReport($report, 0, '');

        self::assertSame($expected, $result->suggestedStatus);
        self::assertSame($uri, $result->sourceType);
    }

    /**
     * A file that is simply missing must not blow up the caller — the upload
     * has to survive a detection stage having a bad day.
     */
    #[Test]
    public function inspectingAMissingFileReturnsNotVerifiable(): void
    {
        $result = $this->subject->inspect('/definitely/not/here.jpg');

        self::assertSame(C2paState::NotVerifiable, $result->c2paState);
    }

    #[Test]
    public function anEmptyBinaryPathIsHandledRatherThanExecuted(): void
    {
        $result = (new C2paService(''))->inspect(self::FIXTURES . 'valid.json');

        self::assertSame(C2paState::NotVerifiable, $result->c2paState);
    }
}
