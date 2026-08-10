<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Model;

use NetThinks\NtAimark\Domain\Enum\DecisionReason;
use NetThinks\NtAimark\Domain\Enum\IconVariant;

/**
 * The outcome of applying the labelling rules to one declaration.
 */
final readonly class LabelDecision
{
    /**
     * @param array<string, string|int> $detailPayload Data for the expandable detail panel
     */
    public function __construct(
        public bool $shouldLabel,
        public DecisionReason $reason,
        public IconVariant $iconVariant = IconVariant::None,
        public string $labelText = '',
        public array $detailPayload = [],
    ) {}

    public static function noLabel(DecisionReason $reason): self
    {
        return new self(shouldLabel: false, reason: $reason);
    }

    /**
     * True while the asset still needs a human decision — the backend counts
     * these as open tasks even though nothing is rendered in the frontend.
     */
    public function isOpenTask(): bool
    {
        return $this->reason === DecisionReason::Unreviewed;
    }
}
