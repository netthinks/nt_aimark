<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Model;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\C2paState;

/**
 * What automatic inspection found in a file.
 *
 * Never a verdict — the status in here is a *suggestion*. Whether it becomes a
 * statement about the content is a human decision.
 */
final readonly class ProvenanceResult
{
    public function __construct(
        public ?AiStatus $suggestedStatus = null,
        public string $system = '',
        public string $vendor = '',
        public string $sourceType = '',
        public int $createdAt = 0,
        public C2paState $c2paState = C2paState::None,
        public string $c2paManifest = '',
        /** Which stage produced the finding: c2pa, xmp or exif. */
        public string $detectedBy = '',
    ) {}

    public static function nothing(C2paState $c2paState = C2paState::None): self
    {
        return new self(c2paState: $c2paState);
    }

    public function hasFinding(): bool
    {
        return $this->suggestedStatus !== null;
    }

    /**
     * Anything at all worth writing back, even without a status suggestion —
     * a C2PA state or a creation date is useful on its own.
     */
    public function hasAnything(): bool
    {
        return $this->hasFinding()
            || $this->c2paState !== C2paState::None
            || $this->createdAt > 0
            || $this->system !== '';
    }

    public function withC2pa(C2paState $state, string $manifest): self
    {
        return new self(
            suggestedStatus: $this->suggestedStatus,
            system: $this->system,
            vendor: $this->vendor,
            sourceType: $this->sourceType,
            createdAt: $this->createdAt,
            c2paState: $state,
            c2paManifest: $manifest,
            detectedBy: $this->detectedBy,
        );
    }
}
