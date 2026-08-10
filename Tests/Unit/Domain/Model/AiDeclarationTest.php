<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Domain\Model;

use NetThinks\NtAimark\Domain\AiActCutoff;
use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\C2paState;
use NetThinks\NtAimark\Domain\Enum\DisclosureMode;
use NetThinks\NtAimark\Domain\Enum\ExemptReason;
use NetThinks\NtAimark\Domain\Enum\IconVariant;
use NetThinks\NtAimark\Domain\Model\AiDeclaration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AiDeclarationTest extends UnitTestCase
{
    #[Test]
    public function fromRecordMapsEveryPersistedField(): void
    {
        $declaration = AiDeclaration::fromRecord([
            'uid' => 42,
            'tx_ntaimark_status' => 2,
            'tx_ntaimark_disclosure' => 1,
            'tx_ntaimark_exempt_reason' => 'satire',
            'tx_ntaimark_icon' => 'generated',
            'tx_ntaimark_label_text' => 'Mit KI erzeugt',
            'tx_ntaimark_system' => 'DALL·E 3',
            'tx_ntaimark_vendor' => 'OpenAI',
            'tx_ntaimark_prompt' => 'a cat',
            'tx_ntaimark_created_at' => 1_790_000_000,
            'tx_ntaimark_reviewer' => 7,
            'tx_ntaimark_reviewed_at' => 1_790_000_100,
            'tx_ntaimark_c2pa_state' => 1,
            'tx_ntaimark_c2pa_manifest' => '{}',
            'tx_ntaimark_source_type' => 'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia',
            'tx_ntaimark_notes' => 'intern',
        ]);

        self::assertSame('sys_file_metadata', $declaration->tableName);
        self::assertSame(42, $declaration->recordUid);
        self::assertSame(AiStatus::Generated, $declaration->status);
        self::assertSame(DisclosureMode::Forced, $declaration->disclosure);
        self::assertSame(ExemptReason::Satire, $declaration->exemptReason);
        self::assertSame(IconVariant::Generated, $declaration->icon);
        self::assertSame('Mit KI erzeugt', $declaration->labelText);
        self::assertSame('DALL·E 3', $declaration->system);
        self::assertSame('OpenAI', $declaration->vendor);
        self::assertSame('a cat', $declaration->prompt);
        self::assertSame(1_790_000_000, $declaration->createdAt);
        self::assertSame(7, $declaration->reviewer);
        self::assertSame(1_790_000_100, $declaration->reviewedAt);
        self::assertSame(C2paState::Valid, $declaration->c2paState);
        self::assertSame('{}', $declaration->c2paManifest);
        self::assertSame('intern', $declaration->notes);
    }

    #[Test]
    public function fromRecordFallsBackToSafeDefaultsForAnEmptyRecord(): void
    {
        $declaration = AiDeclaration::fromRecord([]);

        self::assertSame(AiStatus::Unreviewed, $declaration->status);
        self::assertSame(DisclosureMode::Automatic, $declaration->disclosure);
        self::assertNull($declaration->exemptReason);
        self::assertNull($declaration->icon);
        self::assertSame(C2paState::None, $declaration->c2paState);
    }

    #[Test]
    public function fromRecordIgnoresValuesOutsideTheKnownRange(): void
    {
        $declaration = AiDeclaration::fromRecord([
            'tx_ntaimark_status' => 99,
            'tx_ntaimark_disclosure' => 99,
            'tx_ntaimark_exempt_reason' => 'nonsense',
            'tx_ntaimark_icon' => 'nonsense',
            'tx_ntaimark_c2pa_state' => 99,
        ]);

        self::assertSame(AiStatus::Unreviewed, $declaration->status);
        self::assertSame(DisclosureMode::Automatic, $declaration->disclosure);
        self::assertNull($declaration->exemptReason);
        self::assertNull($declaration->icon);
        self::assertSame(C2paState::None, $declaration->c2paState);
    }

    #[Test]
    public function preCutoffFollowsTheRecordedCreationDate(): void
    {
        $before = AiDeclaration::fromRecord(['tx_ntaimark_created_at' => AiActCutoff::TIMESTAMP - 1]);
        $after = AiDeclaration::fromRecord(['tx_ntaimark_created_at' => AiActCutoff::TIMESTAMP]);
        $unknown = AiDeclaration::fromRecord([]);

        self::assertTrue($before->isPreCutoff());
        self::assertFalse($after->isPreCutoff());
        self::assertFalse($unknown->isPreCutoff());
    }

    #[Test]
    public function effectiveIconPrefersTheEditorialChoice(): void
    {
        $declaration = AiDeclaration::fromRecord([
            'tx_ntaimark_status' => AiStatus::Generated->value,
            'tx_ntaimark_icon' => IconVariant::Basic->value,
        ]);

        self::assertSame(IconVariant::Basic, $declaration->effectiveIcon());
    }

    #[Test]
    public function effectiveIconDerivesFromStatusWhenNothingWasChosen(): void
    {
        $declaration = AiDeclaration::fromRecord(['tx_ntaimark_status' => AiStatus::Modified->value]);

        self::assertSame(IconVariant::Modified, $declaration->effectiveIcon());
    }

    /**
     * "No icon" is an editorial decision (e.g. a text-only label) and must not
     * be overruled by the status default.
     */
    #[Test]
    public function effectiveIconKeepsAnExplicitlyChosenNone(): void
    {
        $declaration = AiDeclaration::fromRecord([
            'tx_ntaimark_status' => AiStatus::Generated->value,
            'tx_ntaimark_icon' => IconVariant::None->value,
        ]);

        self::assertSame(IconVariant::None, $declaration->effectiveIcon());
    }

    #[Test]
    public function aSuggestionIsNeverConsideredReviewed(): void
    {
        $declaration = AiDeclaration::fromRecord([
            'tx_ntaimark_status' => AiStatus::Suggested->value,
            'tx_ntaimark_reviewed_at' => 1_790_000_000,
        ]);

        self::assertFalse($declaration->isReviewed());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function systemLabels(): array
    {
        return [
            'system and vendor' => ['DALL·E 3', 'OpenAI', 'DALL·E 3 (OpenAI)'],
            'system only' => ['DALL·E 3', '', 'DALL·E 3'],
            'vendor only' => ['', 'OpenAI', 'OpenAI'],
            'nothing recorded' => ['', '', ''],
        ];
    }

    #[Test]
    #[DataProvider('systemLabels')]
    public function systemLabelDegradesWithMissingData(string $system, string $vendor, string $expected): void
    {
        $declaration = AiDeclaration::fromRecord([
            'tx_ntaimark_system' => $system,
            'tx_ntaimark_vendor' => $vendor,
        ]);

        self::assertSame($expected, $declaration->systemLabel());
    }
}
