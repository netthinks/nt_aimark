<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\EventListener;

use NetThinks\NtAimark\Service\ExtensionSettings;
use NetThinks\NtAimark\Service\MetadataPreservationService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;

/**
 * Writes the XMP packet back into a freshly processed image.
 *
 * Runs once per processed variant, at processing time — the result is cached
 * like any other processed file.
 */
#[AsEventListener(identifier: 'nt-aimark/after-file-processing')]
final readonly class AfterFileProcessingListener
{
    public function __construct(
        private MetadataPreservationService $metadataPreservation,
        private ExtensionSettings $settings,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(AfterFileProcessingEvent $event): void
    {
        $processedFile = $event->getProcessedFile();

        // Nothing was produced: the original is being served unchanged and
        // still carries its own metadata.
        if (!$processedFile->isProcessed() || $processedFile->usesOriginalFile()) {
            return;
        }

        if (!$this->settings->preserveMetadata()) {
            return;
        }

        try {
            $source = $event->getFile()->getForLocalProcessing(false);
            $target = $processedFile->getForLocalProcessing(false);

            $this->metadataPreservation->restoreXmp($source, $target);
        } catch (\Throwable $exception) {
            // A missing packet must never keep an image from being delivered.
            $this->logger->notice('Could not restore XMP in the processed file.', [
                'file' => $processedFile->getIdentifier(),
                'exception' => $exception,
            ]);
        }
    }
}
