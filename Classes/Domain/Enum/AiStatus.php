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

    /**
     * Bootstrap contextual class for the badge in the backend list.
     *
     * The distinction the colour carries is "does this still need someone" —
     * not "is AI involved". A confirmed AI use is a finished, correct state
     * and is not coloured as a problem.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Unreviewed => 'bg-secondary',
            self::Suggested => 'bg-warning',
            self::NoAi => 'bg-success',
            self::Generated, self::Modified => 'bg-info',
            self::UnknownOrigin => 'bg-light text-dark',
        };
    }

    /**
     * Segment colour in the module's charts.
     *
     * Fixed values rather than CSS variables: the chart is an SVG built from
     * presentation attributes so that no Content Security Policy can drop it,
     * and these mid-tones stay legible on the light and the dark backend alike.
     * The chart never carries meaning by colour alone — the legend beside it
     * names every segment and its count.
     */
    public function chartColour(): string
    {
        return match ($this) {
            self::Unreviewed => '#8a9199',
            self::Suggested => '#e0a800',
            self::NoAi => '#2f9e5f',
            self::Generated => '#1f8fb0',
            self::Modified => '#5a4fcf',
            self::UnknownOrigin => '#c2c8ce',
        };
    }
}
