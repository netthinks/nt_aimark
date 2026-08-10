<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\EventListener;

use NetThinks\NtAimark\Service\ProvenanceExtractorService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileReplacedEvent;
use TYPO3\CMS\Core\Resource\File;

/**
 * Re-inspects a file whose contents were replaced.
 *
 * The declaration describes the content, not the file name — new content means
 * the previous finding no longer applies.
 */
#[AsEventListener(identifier: 'nt-aimark/after-file-replaced')]
final readonly class AfterFileReplacedListener
{
    public function __construct(
        private ProvenanceExtractorService $provenanceExtractor,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(AfterFileReplacedEvent $event): void
    {
        $file = $event->getFile();

        if (!$file instanceof File) {
            return;
        }

        try {
            $this->provenanceExtractor->applyTo($file);
        } catch (\Throwable $exception) {
            $this->logger->warning('Provenance detection failed for the replaced file.', [
                'file' => $file->getIdentifier(),
                'exception' => $exception,
            ]);
        }
    }
}
