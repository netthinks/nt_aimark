<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Enum;

/**
 * Degree of AI involvement recorded for a text record.
 *
 * Persisted in `tx_ntaimark_text_status` on pages, tt_content and any
 * additionally configured table.
 */
enum TextStatus: int
{
    case NoAi = 0;

    /** AI produced a draft that was reworked by a human. */
    case AiDraftRevised = 1;

    case AiGenerated = 2;

    public function isAiUse(): bool
    {
        return $this !== self::NoAi;
    }

    public function labelKey(): string
    {
        return 'LLL:EXT:nt_aimark/Resources/Private/Language/locallang_db.xlf:textStatus.' . $this->name;
    }
}
