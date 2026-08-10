<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\EventListener;

use NetThinks\NtAimark\Service\MetaDataAuditRecorder;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileMetaDataUpdatedEvent;

/**
 * Records transparency changes made through the file API.
 *
 * That covers other extensions writing metadata — nt_ai setting an alt text,
 * for instance. Edits in the backend form do not pass through here; they go
 * through the DataHandler and are covered by MetaDataChangeHook.
 */
#[AsEventListener(identifier: 'nt-aimark/metadata-audit')]
final readonly class MetaDataAuditListener
{
    public function __construct(
        private MetaDataAuditRecorder $recorder,
    ) {}

    public function __invoke(AfterFileMetaDataUpdatedEvent $event): void
    {
        $this->recorder->record($event->getMetaDataUid(), $event->getRecord());
    }
}
