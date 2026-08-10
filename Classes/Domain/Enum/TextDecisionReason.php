<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Enum;

/**
 * Why a text is, or is not, disclosed.
 *
 * Written to the audit trail alongside the media reasons.
 */
enum TextDecisionReason: string
{
    case NoAi = 'no_ai';

    /**
     * The obligation for texts only covers matters of public interest.
     */
    case NotPublicInterest = 'not_public_interest';

    /**
     * Human review took place and a responsible person is named — the
     * exception applies.
     */
    case EditorialControl = 'editorial_control';

    /**
     * Review is claimed but nobody is named for it. The exception is not
     * complete, so the text is still disclosed.
     */
    case EditorialControlIncomplete = 'editorial_control_incomplete';

    case RuleDefault = 'rule_default';
}
