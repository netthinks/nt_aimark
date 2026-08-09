<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\Model\AiDeclaration;
use NetThinks\NtAimark\Domain\Model\LabelDecision;

/**
 * Adjusts a labelling decision after the rules have run.
 *
 * Register an implementation with the tag `nt_aimark.label_decision_modifier`;
 * all of them run in registration order, each seeing the previous result.
 *
 * The rules in DisclosureRuleService stay the single source of the decision —
 * a modifier refines it, it does not replace the reasoning. Whatever it
 * returns is what gets rendered, so a modifier that turns a "no label" into a
 * label had better have a good reason and should say so in the decision's
 * reason code.
 *
 * @api This is an extension point. It stays stable within a major version.
 */
interface LabelDecisionModifierInterface
{
    public function modify(AiDeclaration $declaration, LabelDecision $decision): LabelDecision;
}
