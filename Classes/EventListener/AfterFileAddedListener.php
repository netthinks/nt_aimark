<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\EventListener;

use NetThinks\NtAimark\Service\ProvenanceExtractorService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedEvent;
use TYPO3\CMS\Core\Resource\File;

/**
 * Inspects a newly uploaded file for provenance data.
 *
 * Wrapped in a catch-all: an upload must never fail because a detection stage
 * had a bad day.
 */
#[AsEventListener(identifier: 'nt-aimark/after-file-added')]
final readonly class AfterFileAddedListener
{
    public function __construct(
        private ProvenanceExtractorService $provenanceExtractor,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(AfterFileAddedEvent $event): void
    {
        $file = $event->getFile();

        if (!$file instanceof File) {
            return;
        }

        try {
            $this->provenanceExtractor->applyTo($file);
        } catch (\Throwable $exception) {
            $this->logger->warning('Provenance detection failed for the uploaded file.', [
                'file' => $file->getIdentifier(),
                'exception' => $exception,
            ]);
        }
    }
}
