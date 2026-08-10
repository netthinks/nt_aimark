<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\DataHandling;

use NetThinks\NtAimark\Service\MetaDataAuditRecorder;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Records transparency changes made in the backend form.
 *
 * A DataHandler hook rather than a PSR-14 listener, and not by preference:
 * TYPO3 v14 dispatches no event for record updates, and AfterFileMetaDataUpdatedEvent
 * only fires for writes through the file API — a form save writes the table
 * directly and would leave no trace. The hook is not deprecated in v14.
 *
 * @see MetaDataAuditRecorder for the shared comparison
 */
final readonly class MetaDataChangeHook
{
    public function __construct(
        private MetaDataAuditRecorder $recorder,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $fieldArray The fields the form actually submitted
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        string|int $id,
        array $fieldArray,
        DataHandler $dataHandler,
    ): void {
        if ($table !== MetaDataAuditRecorder::TABLE) {
            return;
        }

        // A newly created record carries a placeholder id until the write is
        // through; the real one is in the substitution map.
        $recordUid = (int) ($dataHandler->substNEWwithIDs[$id] ?? $id);

        try {
            $this->recorder->record($recordUid, $fieldArray, $status === 'new' ? 'create' : 'update');
        } catch (\Throwable $exception) {
            // An exception in here aborts the whole save. A gap in the trail is
            // bad; an editor who cannot save at all is worse. The gap is
            // visible in the log.
            $this->logger->warning('Could not record the transparency change.', [
                'record' => $recordUid,
                'exception' => $exception,
            ]);
        }
    }
}
