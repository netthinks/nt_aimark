<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\EventListener;

use NetThinks\NtAimark\Domain\Repository\DeclarationRepository;
use NetThinks\NtAimark\Service\AiMarkSettingsFactory;
use NetThinks\NtAimark\Service\DisclosureRuleService;
use NetThinks\NtAimark\Service\ExtensionSettings;
use NetThinks\NtAimark\Service\IconCompositorInterface;
use NetThinks\NtAimark\Service\MetadataPreservationService;
use NetThinks\NtAimark\Service\NullIconCompositor;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;

/**
 * Finishes a freshly processed image: XMP back in, and the place where the
 * disclosure icon can be burnt into the picture.
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
        private IconCompositorInterface $iconCompositor,
        private DeclarationRepository $declarationRepository,
        private DisclosureRuleService $disclosureRules,
        private AiMarkSettingsFactory $settingsFactory,
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

        $this->burnInIcon($event);

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

    /**
     * The place where a second package can write the icon into the picture.
     *
     * The core package registers a compositor that passes through, and the
     * check for it comes first on purpose: without it every processed image —
     * every thumbnail, every size — would cost a declaration lookup for a
     * feature that is not installed.
     */
    private function burnInIcon(AfterFileProcessingEvent $event): void
    {
        if ($this->iconCompositor instanceof NullIconCompositor) {
            return;
        }

        try {
            $declaration = $this->declarationRepository->forFile($event->getFile());
            $decision = $this->disclosureRules->resolve($declaration, $this->settingsFactory->fromRequest(null));

            if (!$decision->shouldLabel) {
                return;
            }

            $processedFile = $event->getProcessedFile();
            $target = $processedFile->getForLocalProcessing(false);
            $result = $this->iconCompositor->composite(
                $processedFile,
                $decision->iconVariant,
                $this->settingsFactory->fromRequest(null)->badgePosition,
            );

            if ($result !== $target && is_file($result)) {
                copy($result, $target);
            }
        } catch (\Throwable $exception) {
            // A picture that cannot be stamped is still a picture. It must be
            // delivered, and the label in the page markup remains either way.
            $this->logger->notice('Could not burn the disclosure icon into the processed file.', [
                'file' => $event->getProcessedFile()->getIdentifier(),
                'exception' => $exception,
            ]);
        }
    }
}
