<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Event;

/**
 * Announces that the recorded AI status of a record changed.
 *
 * Dispatched from the audit trail, so it covers every route: the backend form,
 * bulk editing, automatic detection, the CLI, and reports from sibling
 * extensions. If a change is in the trail, this event fired.
 *
 * Useful for anything that has to react rather than decide — notifying a
 * hosted service, invalidating a cache, feeding a dashboard.
 *
 * @api This is an extension point. It stays stable within a major version.
 */
final readonly class AfterStatusChangedEvent
{
    public function __construct(
        public string $tableName,
        public int $recordUid,
        /** `tx_ntaimark_status` for media, `tx_ntaimark_text_status` for texts. */
        public string $fieldName,
        public string $oldValue,
        public string $newValue,
        /** One of the AuditService::SOURCE_* constants. */
        public string $source,
    ) {}
}
