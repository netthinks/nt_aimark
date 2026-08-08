<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Service;

use NetThinks\NtAimark\Domain\Enum\TextDecisionReason;
use NetThinks\NtAimark\Domain\Enum\TextStatus;
use NetThinks\NtAimark\Domain\Model\TextDeclaration;
use NetThinks\NtAimark\Service\TextDisclosureRuleService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class TextDisclosureRuleServiceTest extends UnitTestCase
{
    private TextDisclosureRuleService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new TextDisclosureRuleService();
    }

    private function declaration(
        TextStatus $status,
        bool $publicInterest,
        bool $editorialControl,
        string $responsible = '',
    ): TextDeclaration {
        return new TextDeclaration(
            tableName: 'tt_content',
            recordUid: 1,
            status: $status,
            publicInterest: $publicInterest,
            editorialControl: $editorialControl,
            responsible: $responsible,
        );
    }

    /**
     * Every combination of status × public interest × editorial control, with
     * the "named person" case split out because it is what makes the exception
     * hold or not.
     *
     * @return array<string, array{TextStatus, bool, bool, string, bool, TextDecisionReason}>
     */
    public static function decisionMatrix(): array
    {
        $cases = [];

        // Rule 1 — no AI, nothing else matters.
        foreach ([false, true] as $publicInterest) {
            foreach ([false, true] as $editorialControl) {
                $cases[sprintf('no AI / public=%s / control=%s', var_export($publicInterest, true), var_export($editorialControl, true))] =
                    [TextStatus::NoAi, $publicInterest, $editorialControl, 'Redaktion', false, TextDecisionReason::NoAi];
            }
        }

        // Rule 2 — AI involved but not a matter of public interest.
        foreach ([TextStatus::AiDraftRevised, TextStatus::AiGenerated] as $status) {
            foreach ([false, true] as $editorialControl) {
                $cases[sprintf('%s / not public / control=%s', $status->name, var_export($editorialControl, true))] =
                    [$status, false, $editorialControl, 'Redaktion', false, TextDecisionReason::NotPublicInterest];
            }
        }

        // Rules 3 to 5 — public interest, so the obligation is in play.
        foreach ([TextStatus::AiDraftRevised, TextStatus::AiGenerated] as $status) {
            $cases[$status->name . ' / public / reviewed and named'] =
                [$status, true, true, 'Dietmar Engler', false, TextDecisionReason::EditorialControl];

            $cases[$status->name . ' / public / reviewed but nobody named'] =
                [$status, true, true, '', true, TextDecisionReason::EditorialControlIncomplete];

            $cases[$status->name . ' / public / not reviewed'] =
                [$status, true, false, '', true, TextDecisionReason::RuleDefault];

            // A name without the tick box is not a review either.
            $cases[$status->name . ' / public / named but not reviewed'] =
                [$status, true, false, 'Dietmar Engler', true, TextDecisionReason::RuleDefault];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('decisionMatrix')]
    public function resolveFollowsTheDecisionOrder(
        TextStatus $status,
        bool $publicInterest,
        bool $editorialControl,
        string $responsible,
        bool $expectedShouldLabel,
        TextDecisionReason $expectedReason,
    ): void {
        $decision = $this->subject->resolve(
            $this->declaration($status, $publicInterest, $editorialControl, $responsible),
        );

        self::assertSame($expectedReason, $decision->reason);
        self::assertSame($expectedShouldLabel, $decision->shouldLabel);
    }

    /**
     * The point of the exception is that somebody answers for the review. A
     * tick box on its own documents nothing, so it must not switch the
     * disclosure off.
     */
    #[Test]
    public function aClaimedReviewWithoutANamedPersonDoesNotExempt(): void
    {
        $decision = $this->subject->resolve(
            $this->declaration(TextStatus::AiGenerated, publicInterest: true, editorialControl: true),
        );

        self::assertTrue($decision->shouldLabel);
        self::assertTrue($decision->isIncompleteExemption());
    }

    #[Test]
    public function aCompleteReviewCarriesTheResponsiblePersonThrough(): void
    {
        $decision = $this->subject->resolve(
            $this->declaration(TextStatus::AiGenerated, true, true, 'Dietmar Engler'),
        );

        self::assertFalse($decision->shouldLabel);
        self::assertSame('Dietmar Engler', $decision->responsible);
        self::assertFalse($decision->isIncompleteExemption());
    }

    #[Test]
    public function whitespaceIsNotAName(): void
    {
        $declaration = TextDeclaration::fromRecord([
            'uid' => 1,
            'tx_ntaimark_text_status' => TextStatus::AiGenerated->value,
            'tx_ntaimark_public_interest' => 1,
            'tx_ntaimark_editorial_control' => 1,
            'tx_ntaimark_responsible' => '   ',
        ], 'tt_content');

        self::assertTrue($this->subject->resolve($declaration)->shouldLabel);
    }

    /**
     * Counting rows says little; what matters is that no combination of the
     * three persisted flags is missing from the matrix.
     */
    #[Test]
    public function theMatrixCoversEveryCombinationOfThePersistedFlags(): void
    {
        $covered = [];

        foreach (self::decisionMatrix() as [$status, $publicInterest, $editorialControl]) {
            $covered[sprintf('%s|%d|%d', $status->name, (int) $publicInterest, (int) $editorialControl)] = true;
        }

        foreach (TextStatus::cases() as $status) {
            foreach ([0, 1] as $publicInterest) {
                foreach ([0, 1] as $editorialControl) {
                    self::assertArrayHasKey(
                        sprintf('%s|%d|%d', $status->name, $publicInterest, $editorialControl),
                        $covered,
                        sprintf('Untested: %s, public=%d, control=%d', $status->name, $publicInterest, $editorialControl),
                    );
                }
            }
        }
    }

    #[Test]
    public function fromRecordFallsBackToTheSafestDefaults(): void
    {
        $declaration = TextDeclaration::fromRecord([], 'pages');

        self::assertSame(TextStatus::NoAi, $declaration->status);
        self::assertFalse($declaration->publicInterest);
        self::assertFalse($declaration->editorialControl);
        self::assertSame('', $declaration->responsible);
    }

    #[Test]
    public function fromRecordIgnoresAnUnknownStatus(): void
    {
        $declaration = TextDeclaration::fromRecord(['tx_ntaimark_text_status' => 99], 'pages');

        self::assertSame(TextStatus::NoAi, $declaration->status);
    }
}
