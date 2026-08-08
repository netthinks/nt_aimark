<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Model;

use NetThinks\NtAimark\Domain\Enum\TextDecisionReason;
use NetThinks\NtAimark\Domain\Enum\TextStatus;

/**
 * The outcome of applying the text rules to one record.
 */
final readonly class TextLabelDecision
{
    public function __construct(
        public bool $shouldLabel,
        public TextDecisionReason $reason,
        public TextStatus $status = TextStatus::NoAi,
        public string $responsible = '',
    ) {}

    public static function noLabel(TextDecisionReason $reason): self
    {
        return new self(shouldLabel: false, reason: $reason);
    }

    /**
     * A claimed review that names nobody. The backend lists these so the gap
     * can be closed rather than sitting there unnoticed.
     */
    public function isIncompleteExemption(): bool
    {
        return $this->reason === TextDecisionReason::EditorialControlIncomplete;
    }
}
