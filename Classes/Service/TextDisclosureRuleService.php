<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\Enum\TextDecisionReason;
use NetThinks\NtAimark\Domain\Enum\TextStatus;
use NetThinks\NtAimark\Domain\Model\TextDeclaration;
use NetThinks\NtAimark\Domain\Model\TextLabelDecision;

/**
 * Decides whether a text carries a disclosure.
 *
 * The obligation for texts is shaped differently from the one for media: it
 * only covers matters of public interest, and it falls away when a human
 * reviewed the text and someone is named for that review.
 *
 * There is deliberately no cutoff rule here. For media the moment of creation
 * decides; for texts the moment of publication counts as well, and a text that
 * is live on a website is being published now. Turning that into an automatic
 * exemption would be a legal reading, and the extension does not make those —
 * editors have the fields to record their own decision.
 */
final readonly class TextDisclosureRuleService
{
    public function resolve(TextDeclaration $declaration): TextLabelDecision
    {
        // 1. Nothing to disclose.
        if ($declaration->status === TextStatus::NoAi) {
            return TextLabelDecision::noLabel(TextDecisionReason::NoAi);
        }

        // 2. The obligation covers matters of public interest, not every text.
        if (!$declaration->publicInterest) {
            return TextLabelDecision::noLabel(TextDecisionReason::NotPublicInterest);
        }

        // 3. Reviewed by a human who is named for it — the exception applies.
        if ($declaration->hasCompleteEditorialControl()) {
            return new TextLabelDecision(
                shouldLabel: false,
                reason: TextDecisionReason::EditorialControl,
                status: $declaration->status,
                responsible: $declaration->responsible,
            );
        }

        // 4. Review is claimed but nobody is named. A tick box documents
        //    nothing, so the exception does not hold and the text is
        //    disclosed — visibly flagged, so the gap can be closed.
        if ($declaration->editorialControl) {
            return new TextLabelDecision(
                shouldLabel: true,
                reason: TextDecisionReason::EditorialControlIncomplete,
                status: $declaration->status,
            );
        }

        return new TextLabelDecision(
            shouldLabel: true,
            reason: TextDecisionReason::RuleDefault,
            status: $declaration->status,
        );
    }
}
