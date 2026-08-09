<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Event;

use NetThinks\NtAimark\Domain\Model\AiDeclaration;
use NetThinks\NtAimark\Domain\Model\LabelDecision;

/**
 * Announces a completed labelling decision.
 *
 * For observing, not for changing: counting, logging, warming a cache. To
 * change the outcome, register a LabelDecisionModifierInterface — that runs
 * in a defined order and is the documented way in.
 *
 * Fires once per resolved file, so a page with many images dispatches this
 * many times. Keep listeners cheap.
 *
 * @api This is an extension point. It stays stable within a major version.
 */
final readonly class AfterLabelDecisionEvent
{
    public function __construct(
        public AiDeclaration $declaration,
        public LabelDecision $decision,
    ) {}
}
