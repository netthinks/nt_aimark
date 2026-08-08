<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Enum;

/**
 * Machine-readable justification for a labelling decision.
 *
 * Written to the audit trail, so the reasoning behind every rendered — and
 * every omitted — label stays reconstructible.
 */
enum DecisionReason: string
{
    /** An editor decided this content does not require disclosure. */
    case ManualExempt = 'manual_exempt';

    /** Created before Art. 50 became applicable. */
    case PreCutoff = 'pre_cutoff';

    /** Nobody has confirmed the status yet — counts as an open task in the backend. */
    case Unreviewed = 'unreviewed';

    case NoAi = 'no_ai';

    /** An editor decided to label regardless of what the rules conclude. */
    case ManualForced = 'manual_forced';

    /** Labelled because a confirmed status says AI was involved. */
    case RuleDefault = 'rule_default';

    case UnknownOrigin = 'unknown_origin';
}
