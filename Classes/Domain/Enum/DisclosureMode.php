<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Enum;

/**
 * Editorial override of the automatic labelling decision.
 *
 * Persisted in `tx_ntaimark_disclosure`.
 */
enum DisclosureMode: int
{
    /** Let DisclosureRuleService decide. */
    case Automatic = 0;

    /** Always label, regardless of what the rule set would conclude. */
    case Forced = 1;

    /** Never label — an editor has assessed the case as not requiring disclosure. */
    case Exempt = 2;

    public function labelKey(): string
    {
        return 'LLL:EXT:nt_aimark/Resources/Private/Language/locallang_db.xlf:disclosure.' . $this->name;
    }
}
