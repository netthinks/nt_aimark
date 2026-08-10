<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Service;

use NetThinks\NtAimark\Domain\AiActCutoff;
use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\DecisionReason;
use NetThinks\NtAimark\Domain\Enum\DisclosureMode;
use NetThinks\NtAimark\Domain\Enum\IconVariant;
use NetThinks\NtAimark\Domain\Model\AiDeclaration;
use NetThinks\NtAimark\Domain\Model\AiMarkSettings;
use NetThinks\NtAimark\Service\DisclosureRuleService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class DisclosureRuleServiceTest extends UnitTestCase
{
    private DisclosureRuleService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new DisclosureRuleService();
    }

    private function declaration(
        AiStatus $status,
        DisclosureMode $disclosure,
        bool $preCutoff,
        ?IconVariant $icon = null,
    ): AiDeclaration {
        return new AiDeclaration(
            tableName: 'sys_file_metadata',
            recordUid: 1,
            status: $status,
            disclosure: $disclosure,
            icon: $icon,
            createdAt: $preCutoff ? AiActCutoff::TIMESTAMP - 86400 : AiActCutoff::TIMESTAMP + 86400,
        );
    }

    /**
     * The complete decision matrix: every status × every disclosure mode ×
     * before/after the cutoff, with "label unknown origin" switched off.
     *
     * The expectations are written out rather than computed, so a change to the
     * rule order shows up here as a failing row instead of silently agreeing.
     *
     * @return array<string, array{AiStatus, DisclosureMode, bool, bool, DecisionReason}>
     */
    public static function decisionMatrix(): array
    {
        $cases = [];

        // Rule 1 — an editorial exemption wins over everything else.
        foreach (AiStatus::cases() as $status) {
            foreach ([false, true] as $preCutoff) {
                $cases[sprintf('exempt / %s / preCutoff=%s', $status->name, var_export($preCutoff, true))] =
                    [$status, DisclosureMode::Exempt, $preCutoff, false, DecisionReason::ManualExempt];
            }
        }

        // Rule 2 — content predating the obligation, including "always label".
        foreach (AiStatus::cases() as $status) {
            foreach ([DisclosureMode::Automatic, DisclosureMode::Forced] as $disclosure) {
                $cases[sprintf('preCutoff / %s / %s', $status->name, $disclosure->name)] =
                    [$status, $disclosure, true, false, DecisionReason::PreCutoff];
            }
        }

        // Rules 3–7, after the cutoff, disclosure = automatic.
        $automatic = [
            [AiStatus::Unreviewed, false, DecisionReason::Unreviewed],
            [AiStatus::Suggested, false, DecisionReason::Unreviewed],
            [AiStatus::NoAi, false, DecisionReason::NoAi],
            [AiStatus::Generated, true, DecisionReason::RuleDefault],
            [AiStatus::Modified, true, DecisionReason::RuleDefault],
            [AiStatus::UnknownOrigin, false, DecisionReason::UnknownOrigin],
        ];
        foreach ($automatic as [$status, $shouldLabel, $reason]) {
            $cases['automatic / ' . $status->name] =
                [$status, DisclosureMode::Automatic, false, $shouldLabel, $reason];
        }

        // Rules 3–6, after the cutoff, disclosure = forced. "Always label" does
        // not override an unreviewed record or a record marked as AI-free.
        $forced = [
            [AiStatus::Unreviewed, false, DecisionReason::Unreviewed],
            [AiStatus::Suggested, false, DecisionReason::Unreviewed],
            [AiStatus::NoAi, false, DecisionReason::NoAi],
            [AiStatus::Generated, true, DecisionReason::ManualForced],
            [AiStatus::Modified, true, DecisionReason::ManualForced],
            [AiStatus::UnknownOrigin, true, DecisionReason::ManualForced],
        ];
        foreach ($forced as [$status, $shouldLabel, $reason]) {
            $cases['forced / ' . $status->name] =
                [$status, DisclosureMode::Forced, false, $shouldLabel, $reason];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('decisionMatrix')]
    public function resolveFollowsTheDecisionOrder(
        AiStatus $status,
        DisclosureMode $disclosure,
        bool $preCutoff,
        bool $expectedShouldLabel,
        DecisionReason $expectedReason,
    ): void {
        $decision = $this->subject->resolve(
            $this->declaration($status, $disclosure, $preCutoff),
            new AiMarkSettings(labelUnknownOrigin: false),
        );

        self::assertSame($expectedReason, $decision->reason);
        self::assertSame($expectedShouldLabel, $decision->shouldLabel);
    }

    #[Test]
    public function theMatrixCoversEveryCombination(): void
    {
        $expected = count(AiStatus::cases()) * count(DisclosureMode::cases()) * 2;

        self::assertCount($expected, self::decisionMatrix());
    }

    #[Test]
    public function unknownOriginIsLabelledOnlyWhenTheSiteOptsIn(): void
    {
        $declaration = $this->declaration(AiStatus::UnknownOrigin, DisclosureMode::Automatic, false);

        $off = $this->subject->resolve($declaration, new AiMarkSettings(labelUnknownOrigin: false));
        $on = $this->subject->resolve($declaration, new AiMarkSettings(labelUnknownOrigin: true));

        self::assertFalse($off->shouldLabel);
        self::assertTrue($on->shouldLabel);
        self::assertSame(DecisionReason::UnknownOrigin, $on->reason);
        self::assertSame(IconVariant::Basic, $on->iconVariant);
    }

    /**
     * The setting must not leak into any other branch of the rule set.
     *
     * @return array<string, array{AiStatus, DisclosureMode, bool}>
     */
    public static function unaffectedByTheUnknownOriginSetting(): array
    {
        return [
            'exempt' => [AiStatus::Generated, DisclosureMode::Exempt, false],
            'pre-cutoff' => [AiStatus::Generated, DisclosureMode::Automatic, true],
            'unreviewed' => [AiStatus::Unreviewed, DisclosureMode::Automatic, false],
            'suggestion' => [AiStatus::Suggested, DisclosureMode::Automatic, false],
            'no AI' => [AiStatus::NoAi, DisclosureMode::Automatic, false],
            'generated' => [AiStatus::Generated, DisclosureMode::Automatic, false],
        ];
    }

    #[Test]
    #[DataProvider('unaffectedByTheUnknownOriginSetting')]
    public function theUnknownOriginSettingChangesNothingElse(
        AiStatus $status,
        DisclosureMode $disclosure,
        bool $preCutoff,
    ): void {
        $declaration = $this->declaration($status, $disclosure, $preCutoff);

        $off = $this->subject->resolve($declaration, new AiMarkSettings(labelUnknownOrigin: false));
        $on = $this->subject->resolve($declaration, new AiMarkSettings(labelUnknownOrigin: true));

        self::assertSame($off->reason, $on->reason);
        self::assertSame($off->shouldLabel, $on->shouldLabel);
    }

    /**
     * A missing creation date must not be read as "before the cutoff" — that
     * would exempt every asset nobody has dated.
     */
    #[Test]
    public function anUnknownCreationDateDoesNotExempt(): void
    {
        $declaration = new AiDeclaration(
            tableName: 'sys_file_metadata',
            recordUid: 1,
            status: AiStatus::Generated,
            createdAt: 0,
        );

        $decision = $this->subject->resolve($declaration, new AiMarkSettings());

        self::assertTrue($decision->shouldLabel);
        self::assertSame(DecisionReason::RuleDefault, $decision->reason);
    }

    /**
     * @return array<string, array{AiStatus, ?IconVariant, IconVariant}>
     */
    public static function iconChoices(): array
    {
        return [
            'generated derives its icon' => [AiStatus::Generated, null, IconVariant::Generated],
            'modified derives its icon' => [AiStatus::Modified, null, IconVariant::Modified],
            'editorial choice wins' => [AiStatus::Generated, IconVariant::Basic, IconVariant::Basic],
            'text-only label is respected' => [AiStatus::Generated, IconVariant::None, IconVariant::None],
        ];
    }

    #[Test]
    #[DataProvider('iconChoices')]
    public function theIconFollowsTheEditorialChoiceThenTheStatus(
        AiStatus $status,
        ?IconVariant $chosen,
        IconVariant $expected,
    ): void {
        $decision = $this->subject->resolve(
            $this->declaration($status, DisclosureMode::Automatic, false, $chosen),
            new AiMarkSettings(),
        );

        self::assertSame($expected, $decision->iconVariant);
    }

    #[Test]
    public function unreviewedRecordsAreReportedAsOpenTasks(): void
    {
        $unreviewed = $this->subject->resolve(
            $this->declaration(AiStatus::Suggested, DisclosureMode::Automatic, false),
            new AiMarkSettings(),
        );
        $settled = $this->subject->resolve(
            $this->declaration(AiStatus::NoAi, DisclosureMode::Automatic, false),
            new AiMarkSettings(),
        );

        self::assertTrue($unreviewed->isOpenTask());
        self::assertFalse($settled->isOpenTask());
    }

    #[Test]
    public function theDetailPayloadCarriesOnlyPubliclyShowableData(): void
    {
        $declaration = new AiDeclaration(
            tableName: 'sys_file_metadata',
            recordUid: 1,
            status: AiStatus::Generated,
            system: 'DALL·E 3',
            vendor: 'OpenAI',
            prompt: 'a photorealistic cat',
            createdAt: AiActCutoff::TIMESTAMP + 86400,
            sourceType: 'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia',
            notes: 'internal remark',
        );

        $payload = $this->subject->resolve($declaration, new AiMarkSettings())->detailPayload;

        self::assertSame('DALL·E 3 (OpenAI)', $payload['system']);
        self::assertSame(AiActCutoff::TIMESTAMP + 86400, $payload['createdAt']);
        self::assertArrayNotHasKey('prompt', $payload);
        self::assertArrayNotHasKey('notes', $payload);
    }

    #[Test]
    public function theDetailPayloadOmitsWhatWasNeverRecorded(): void
    {
        $decision = $this->subject->resolve(
            $this->declaration(AiStatus::Generated, DisclosureMode::Automatic, false),
            new AiMarkSettings(),
        );

        self::assertArrayNotHasKey('system', $decision->detailPayload);
        self::assertArrayNotHasKey('sourceType', $decision->detailPayload);
    }

    #[Test]
    public function aDecisionAgainstLabellingCarriesNoLabelData(): void
    {
        $decision = $this->subject->resolve(
            $this->declaration(AiStatus::Generated, DisclosureMode::Exempt, false),
            new AiMarkSettings(),
        );

        self::assertFalse($decision->shouldLabel);
        self::assertSame(IconVariant::None, $decision->iconVariant);
        self::assertSame('', $decision->labelText);
        self::assertSame([], $decision->detailPayload);
    }
}
