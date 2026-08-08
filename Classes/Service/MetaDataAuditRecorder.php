<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

/**
 * Records changes to the transparency fields of a file, whoever made them.
 *
 * Two entry points feed this: the FAL event for writes through the file API,
 * and the DataHandler hook for edits in the backend form. TYPO3 v14 offers no
 * PSR-14 event for the second case, and both paths have to be covered — the
 * form is where editors actually work, and that is precisely what the evidence
 * trail exists for.
 *
 * Double entries are avoided without a flag: the previous value comes from the
 * trail itself, so a change the extension already logged explicitly shows no
 * difference here and produces nothing.
 */
final readonly class MetaDataAuditRecorder
{
    public const TABLE = 'sys_file_metadata';

    /**
     * The fields worth keeping evidence of, with the value they hold when
     * nobody has touched them. Without the defaults, an unrelated save (an alt
     * text, say) would look like a change the first time round.
     *
     * @var array<string, string>
     */
    public const WATCHED = [
        'tx_ntaimark_status' => '0',
        'tx_ntaimark_disclosure' => '0',
        'tx_ntaimark_exempt_reason' => '',
        'tx_ntaimark_icon' => '',
        'tx_ntaimark_label_text' => '',
        'tx_ntaimark_system' => '',
        'tx_ntaimark_vendor' => '',
        'tx_ntaimark_created_at' => '0',
        'tx_ntaimark_c2pa_state' => '0',
        'tx_ntaimark_source_type' => '',
        'tx_ntaimark_notes' => '',
    ];

    public function __construct(
        private AuditService $auditService,
    ) {}

    /**
     * @param array<string, mixed> $record Whole record or just the changed fields
     */
    public function record(int $metaDataUid, array $record, string $action = 'update'): void
    {
        if ($metaDataUid <= 0) {
            return;
        }

        $present = array_intersect_key(self::WATCHED, $record);

        if ($present === []) {
            return;
        }

        $known = $this->auditService->lastKnownValues(self::TABLE, $metaDataUid, array_keys($present));

        foreach ($present as $field => $default) {
            $new = (string) $record[$field];
            $old = $known[$field] ?? $default;

            if ($old === $new) {
                continue;
            }

            $this->auditService->log(
                self::TABLE,
                $metaDataUid,
                $action,
                AuditService::SOURCE_MANUAL,
                $field,
                $old,
                $new,
            );
        }
    }
}
