<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Enum;

/**
 * Degree of AI involvement recorded for a media asset.
 *
 * The numeric values are persisted in `tx_ntaimark_status` and must never be
 * reordered — existing records would silently change meaning.
 */
enum AiStatus: int
{
    case Unreviewed = 0;
    case NoAi = 1;
    case Generated = 2;
    case Modified = 3;
    case UnknownOrigin = 4;

    /**
     * Result of automatic provenance extraction, not yet confirmed by a human.
     *
     * Stating "this content is AI generated" is a legal assertion, so a
     * suggestion never reaches the frontend on its own.
     */
    case Suggested = 5;

    /**
     * True while a human still has to look at the record.
     */
    public function requiresReview(): bool
    {
        return $this === self::Unreviewed || $this === self::Suggested;
    }

    /**
     * True only for states a human has confirmed as involving AI.
     */
    public function isConfirmedAiUse(): bool
    {
        return $this === self::Generated || $this === self::Modified;
    }

    public function labelKey(): string
    {
        return 'LLL:EXT:nt_aimark/Resources/Private/Language/locallang_db.xlf:status.' . $this->name;
    }
}
