<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Model;

use NetThinks\NtAimark\Domain\Enum\TextStatus;

/**
 * What was recorded about the AI involvement in a text record.
 */
final readonly class TextDeclaration
{
    public const FIELD_PREFIX = 'tx_ntaimark_';

    public function __construct(
        public string $tableName,
        public int $recordUid,
        public TextStatus $status = TextStatus::NoAi,
        /** A matter of public interest, which is what the obligation covers. */
        public bool $publicInterest = false,
        /** Human review took place. */
        public bool $editorialControl = false,
        /** The person answering for that review. */
        public string $responsible = '',
    ) {}

    /**
     * @param array<string, mixed> $record
     */
    public static function fromRecord(array $record, string $tableName): self
    {
        $get = static fn(string $field): mixed => $record[self::FIELD_PREFIX . $field] ?? null;

        return new self(
            tableName: $tableName,
            recordUid: (int) ($record['uid'] ?? 0),
            status: TextStatus::tryFrom((int) $get('text_status')) ?? TextStatus::NoAi,
            publicInterest: (bool) (int) $get('public_interest'),
            editorialControl: (bool) (int) $get('editorial_control'),
            responsible: trim((string) $get('responsible')),
        );
    }

    /**
     * The exception requires both: review took place, and someone is named for
     * it. A tick box on its own documents nothing.
     */
    public function hasCompleteEditorialControl(): bool
    {
        return $this->editorialControl && $this->responsible !== '';
    }
}
