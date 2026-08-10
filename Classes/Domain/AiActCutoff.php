<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain;

/**
 * The date from which Art. 50 of Regulation (EU) 2024/1689 applies.
 *
 * Content created before this date does not have to be labelled retroactively.
 * For image, audio and video the moment of *creation* is what counts, not the
 * moment of publication — which is why the extension keeps its own creation
 * date instead of reusing the record's tstamp or crdate.
 */
final class AiActCutoff
{
    /** 2026-08-02T00:00:00+00:00 */
    public const TIMESTAMP = 1785628800;

    /**
     * True when a recorded creation date falls before the cutoff, i.e. the
     * content predates the obligation.
     *
     * An unset date (0) is not treated as "before the cutoff" — an unknown
     * creation date must not silently exempt content from labelling.
     */
    public static function isBefore(int $createdAt): bool
    {
        return $createdAt > 0 && $createdAt < self::TIMESTAMP;
    }
}
