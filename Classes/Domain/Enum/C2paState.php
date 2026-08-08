<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Enum;

/**
 * Verification result of a C2PA / Content Credentials manifest.
 *
 * Persisted in `tx_ntaimark_c2pa_state`.
 */
enum C2paState: int
{
    /** No manifest present in the file. */
    case None = 0;

    case Valid = 1;

    /** Manifest present but the signature does not match the file. */
    case Broken = 2;

    /** Manifest present, but verification was not possible (e.g. c2patool missing). */
    case NotVerifiable = 3;

    public function labelKey(): string
    {
        return 'LLL:EXT:nt_aimark/Resources/Private/Language/locallang_db.xlf:c2paState.' . $this->name;
    }
}
