<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Enum;

/**
 * Documented reason why a piece of content is not labelled.
 *
 * Persisted in `tx_ntaimark_exempt_reason`; an empty column means "no reason
 * recorded" and maps to null rather than to a case of this enum.
 */
enum ExemptReason: string
{
    /** Created before Art. 50 became applicable — no retroactive labelling. */
    case PreCutoff = 'pre_cutoff';

    /** Obviously unrealistic or cartoon-like depiction. */
    case NotRealistic = 'not_realistic';

    case Artistic = 'artistic';
    case Satire = 'satire';
    case Fiction = 'fiction';

    /** Purely internal content, not made available to the public. */
    case Internal = 'internal';

    /** Assistive editing only (e.g. spell checking) without substantial change. */
    case MinorAssist = 'minor_assist';

    case Other = 'other';

    public static function tryFromDatabase(?string $value): ?self
    {
        return $value === null || $value === '' ? null : self::tryFrom($value);
    }

    public function labelKey(): string
    {
        return 'LLL:EXT:nt_aimark/Resources/Private/Language/locallang_db.xlf:exemptReason.' . $this->name;
    }
}
