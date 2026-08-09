<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\DecisionReason;
use NetThinks\NtAimark\Domain\Enum\DisclosureMode;
use NetThinks\NtAimark\Domain\Model\AiDeclaration;
use NetThinks\NtAimark\Domain\Model\AiMarkSettings;
use NetThinks\NtAimark\Domain\Model\LabelDecision;
use NetThinks\NtAimark\Event\AfterLabelDecisionEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Answers the one question the whole extension exists for: does this asset get
 * a label, and which one?
 *
 * The order of the checks below is load-bearing and must not be rearranged for
 * convenience. Two properties in particular depend on it:
 *
 * - An unconfirmed automatic suggestion is checked before anything that could
 *   produce a label. Rendering a label from a suggestion would state as fact
 *   what no human has confirmed.
 * - "Always label" cannot resurrect content that predates the obligation, is
 *   marked as involving no AI, or has not been reviewed.
 */
final readonly class DisclosureRuleService
{
    /**
     * @param iterable<LabelDecisionModifierInterface> $modifiers
     */
    public function __construct(
        private iterable $modifiers = [],
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    public function resolve(AiDeclaration $declaration, AiMarkSettings $settings): LabelDecision
    {
        $decision = $this->applyRules($declaration, $settings);

        // Modifiers refine the outcome; the rules above remain the single
        // place the reasoning lives.
        foreach ($this->modifiers as $modifier) {
            $decision = $modifier->modify($declaration, $decision);
        }

        $this->eventDispatcher?->dispatch(new AfterLabelDecisionEvent($declaration, $decision));

        return $decision;
    }

    private function applyRules(AiDeclaration $declaration, AiMarkSettings $settings): LabelDecision
    {
        // 1. An editor has assessed this case and decided against disclosure.
        if ($declaration->disclosure === DisclosureMode::Exempt) {
            return LabelDecision::noLabel(DecisionReason::ManualExempt);
        }

        // 2. Content predating Art. 50 is not labelled retroactively.
        if ($declaration->isPreCutoff()) {
            return LabelDecision::noLabel(DecisionReason::PreCutoff);
        }

        // 3. Never render a label nobody has confirmed.
        if ($declaration->status->requiresReview()) {
            return LabelDecision::noLabel(DecisionReason::Unreviewed);
        }

        // 4. Nothing to disclose.
        if ($declaration->status === AiStatus::NoAi) {
            return LabelDecision::noLabel(DecisionReason::NoAi);
        }

        // 5. Editorial override, now that the disqualifying cases are out.
        if ($declaration->disclosure === DisclosureMode::Forced) {
            return $this->label($declaration, DecisionReason::ManualForced);
        }

        // 6. Confirmed AI involvement.
        if ($declaration->status->isConfirmedAiUse()) {
            return $this->label($declaration, DecisionReason::RuleDefault);
        }

        // 7. Origin unknown — "we do not know" is not "AI was involved", so the
        //    site has to opt in before this becomes a label.
        return $settings->labelUnknownOrigin
            ? $this->label($declaration, DecisionReason::UnknownOrigin)
            : LabelDecision::noLabel(DecisionReason::UnknownOrigin);
    }

    private function label(AiDeclaration $declaration, DecisionReason $reason): LabelDecision
    {
        // By this point the status is Generated, Modified or UnknownOrigin, all
        // of which map to an icon. IconVariant::None therefore only ever comes
        // from an editor deliberately choosing a text-only label.
        return new LabelDecision(
            shouldLabel: true,
            reason: $reason,
            iconVariant: $declaration->effectiveIcon(),
            labelText: $declaration->labelText,
            detailPayload: $this->detailPayload($declaration),
        );
    }

    /**
     * Only what a visitor may see. The prompt and internal notes stay out.
     *
     * @return array<string, string|int>
     */
    private function detailPayload(AiDeclaration $declaration): array
    {
        $payload = [];

        if ($declaration->systemLabel() !== '') {
            $payload['system'] = $declaration->systemLabel();
        }
        if ($declaration->createdAt > 0) {
            $payload['createdAt'] = $declaration->createdAt;
        }
        if ($declaration->sourceType !== '') {
            $payload['sourceType'] = $declaration->sourceType;
        }

        return $payload;
    }
}
