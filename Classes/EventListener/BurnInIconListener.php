<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\EventListener;

use NetThinks\NtAimark\Domain\Repository\DeclarationRepository;
use NetThinks\NtAimark\Service\AiMarkSettingsFactory;
use NetThinks\NtAimark\Service\DisclosureRuleService;
use NetThinks\NtAimark\Service\IconCompositorInterface;
use NetThinks\NtAimark\Service\NullIconCompositor;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;

/**
 * The place where a second package can write the icon into the picture.
 *
 * A listener of its own rather than a branch inside the metadata listener:
 * the two share an event and nothing else, and keeping them apart means
 * neither carries the other's dependencies. The core package registers a
 * compositor that passes through, so this costs a single instanceof per
 * processed file while no second package is installed.
 */
#[AsEventListener(identifier: 'nt-aimark/burn-in-icon')]
final readonly class BurnInIconListener
{
    public function __construct(
        private IconCompositorInterface $iconCompositor,
        private DeclarationRepository $declarationRepository,
        private DisclosureRuleService $disclosureRules,
        private AiMarkSettingsFactory $settingsFactory,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(AfterFileProcessingEvent $event): void
    {
        // First, and deliberately: without it every processed image — every
        // thumbnail, every size — would cost a declaration lookup for a
        // feature that is not installed.
        if ($this->iconCompositor instanceof NullIconCompositor) {
            return;
        }

        $processedFile = $event->getProcessedFile();

        if (!$processedFile->isProcessed() || $processedFile->usesOriginalFile()) {
            return;
        }

        try {
            $settings = $this->settingsFactory->fromRequest(null);
            $declaration = $this->declarationRepository->forFile($event->getFile());
            $decision = $this->disclosureRules->resolve($declaration, $settings);

            if (!$decision->shouldLabel) {
                return;
            }

            $target = $processedFile->getForLocalProcessing(false);
            $result = $this->iconCompositor->composite(
                $processedFile,
                $decision->iconVariant,
                $settings->badgePosition,
            );

            if ($result !== $target && is_file($result)) {
                copy($result, $target);
            }
        } catch (\Throwable $exception) {
            // A picture that cannot be stamped is still a picture. It must be
            // delivered, and the label in the page markup remains either way.
            $this->logger->notice('Could not burn the disclosure icon into the processed file.', [
                'file' => $processedFile->getIdentifier(),
                'exception' => $exception,
            ]);
        }
    }
}
