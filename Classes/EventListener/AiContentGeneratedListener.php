<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\EventListener;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\TextStatus;
use NetThinks\NtAimark\Domain\Model\AiDeclaration;
use NetThinks\NtAimark\Event\AiContentGeneratedEvent;
use NetThinks\NtAimark\Service\AuditService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Takes what a sibling extension reports about content it generated.
 *
 * Three decisions worth stating outright:
 *
 * - **Media stays a suggestion.** Even first-hand knowledge does not make the
 *   public statement "this image is AI generated"; that remains a human call,
 *   exactly as with automatic detection.
 * - **Text is recorded as fact.** The reporting extension wrote the text, so
 *   this is knowledge, not a guess. It still produces no disclosure on its
 *   own: that additionally needs "matter of public interest", which only a
 *   person sets.
 * - **An alt text changes nothing about the image.** A description of a
 *   picture says nothing about how the picture came about. Reporting one is
 *   recorded in the trail and leaves the status alone — treating it otherwise
 *   would mark every image with an AI-written alt text as AI generated.
 */
#[AsEventListener(identifier: 'nt-aimark/ai-content-generated')]
final readonly class AiContentGeneratedListener
{
    private const MEDIA_KINDS = [
        AiContentGeneratedEvent::KIND_IMAGE,
        AiContentGeneratedEvent::KIND_AUDIO,
        AiContentGeneratedEvent::KIND_VIDEO,
    ];

    private const KNOWN_SOURCES = [
        AuditService::SOURCE_NT_AI,
        AuditService::SOURCE_NT_LINGUA,
    ];

    public function __construct(
        private ResourceFactory $resourceFactory,
        private ConnectionPool $connectionPool,
        private AuditService $auditService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(AiContentGeneratedEvent $event): void
    {
        if ($event->recordUid <= 0) {
            return;
        }

        try {
            match (true) {
                $event->contentKind === AiContentGeneratedEvent::KIND_ALT_TEXT => $this->recordAltText($event),
                in_array($event->contentKind, self::MEDIA_KINDS, true) => $this->handleMedia($event),
                $event->contentKind === AiContentGeneratedEvent::KIND_TEXT => $this->handleText($event),
                default => null,
            };
        } catch (\Throwable $exception) {
            // A reporting extension must not break because bookkeeping failed.
            $this->logger->warning('Could not record reported AI content.', [
                'table' => $event->tableName,
                'record' => $event->recordUid,
                'exception' => $exception,
            ]);
        }
    }

    private function handleMedia(AiContentGeneratedEvent $event): void
    {
        $file = $this->resolveFile($event);

        if ($file === null) {
            return;
        }

        $metaData = $file->getMetaData();
        $declaration = AiDeclaration::fromRecord($metaData->get());

        // A record a person has settled is never overwritten, not even by a
        // first-hand report.
        if (!$declaration->status->requiresReview()) {
            return;
        }

        $changes = ['tx_ntaimark_status' => AiStatus::Suggested->value];

        if ($event->aiSystem !== '') {
            $changes['tx_ntaimark_system'] = $event->aiSystem;
        }
        if ($event->aiVendor !== '') {
            $changes['tx_ntaimark_vendor'] = $event->aiVendor;
        }
        if ($event->prompt !== null && $event->prompt !== '') {
            $changes['tx_ntaimark_prompt'] = $event->prompt;
        }
        if ($event->generatedAt !== null && $event->generatedAt > 0) {
            $changes['tx_ntaimark_created_at'] = $event->generatedAt;
        }
        // The icon follows the reported degree, so the editor confirming the
        // suggestion does not have to work it out again.
        $changes['tx_ntaimark_icon'] = $event->fullyGenerated ? 'generated' : 'modified';

        $uid = (int) ($metaData->get()['uid'] ?? 0);

        $this->auditService->logChanges(
            'sys_file_metadata',
            $uid,
            'reported',
            $this->source($event),
            $metaData->get(),
            $changes,
        );

        $metaData->add($changes)->save();
    }

    /**
     * Writes the text status directly — see the class comment for why that is
     * not the same kind of claim as with media.
     */
    private function handleText(AiContentGeneratedEvent $event): void
    {
        if (!$this->isAllowedTextTable($event->tableName)) {
            return;
        }

        $status = $event->fullyGenerated ? TextStatus::AiGenerated : TextStatus::AiDraftRevised;

        $connection = $this->connectionPool->getConnectionForTable($event->tableName);
        $current = $connection->select(
            ['uid', 'tx_ntaimark_text_status'],
            $event->tableName,
            ['uid' => $event->recordUid],
        )->fetchAssociative();

        if ($current === false) {
            return;
        }

        $previous = (int) ($current['tx_ntaimark_text_status'] ?? 0);

        if ($previous === $status->value) {
            return;
        }

        $this->auditService->log(
            $event->tableName,
            $event->recordUid,
            'reported',
            $this->source($event),
            'tx_ntaimark_text_status',
            $previous,
            $status->value,
        );

        $connection->update(
            $event->tableName,
            ['tx_ntaimark_text_status' => $status->value],
            ['uid' => $event->recordUid],
            [Connection::PARAM_INT],
        );
    }

    /**
     * Kept as evidence, without touching the declaration.
     */
    private function recordAltText(AiContentGeneratedEvent $event): void
    {
        $this->auditService->log(
            $event->tableName,
            $event->recordUid,
            'alt_text_generated',
            $this->source($event),
            'alternative',
            null,
            $event->aiSystem,
        );
    }

    private function resolveFile(AiContentGeneratedEvent $event): ?File
    {
        try {
            $fileUid = $event->tableName === 'sys_file_metadata'
                ? $this->fileUidForMetaData($event->recordUid)
                : $event->recordUid;

            return $fileUid > 0 ? $this->resourceFactory->getFileObject($fileUid) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fileUidForMetaData(int $metaDataUid): int
    {
        $row = $this->connectionPool
            ->getConnectionForTable('sys_file_metadata')
            ->select(['file'], 'sys_file_metadata', ['uid' => $metaDataUid])
            ->fetchAssociative();

        return $row === false ? 0 : (int) $row['file'];
    }

    /**
     * The table name reaches SQL, so only tables that actually carry the
     * fields are accepted.
     */
    private function isAllowedTextTable(string $tableName): bool
    {
        return isset($GLOBALS['TCA'][$tableName]['columns']['tx_ntaimark_text_status']);
    }

    private function source(AiContentGeneratedEvent $event): string
    {
        return in_array($event->source, self::KNOWN_SOURCES, true)
            ? (string) $event->source
            : AuditService::SOURCE_IMPORT;
    }
}
