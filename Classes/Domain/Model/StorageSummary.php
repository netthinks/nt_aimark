<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Model;

/**
 * How far the review has come in one file storage.
 */
final readonly class StorageSummary
{
    public function __construct(
        public int $storageUid,
        public string $storageName,
        public int $total,
        /** Nobody has looked at these yet. */
        public int $unreviewed,
        /** Automatic detection proposed something that still needs confirming. */
        public int $suggested,
        public int $brokenC2pa,
    ) {}

    // Fluid resolves {summary.reviewed} through getReviewed(); a method named
    // reviewed() would silently render as empty.
    public function getReviewed(): int
    {
        return max(0, $this->total - $this->unreviewed - $this->suggested);
    }

    /**
     * Open items are what the module exists to reduce: everything a human
     * still has to decide.
     */
    public function getOpen(): int
    {
        return $this->unreviewed + $this->suggested;
    }

    public function getReviewedPercent(): int
    {
        return $this->total === 0 ? 100 : (int) round($this->getReviewed() / $this->total * 100);
    }
}
