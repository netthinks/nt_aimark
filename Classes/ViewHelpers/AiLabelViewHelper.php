<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\ViewHelpers;

use NetThinks\NtAimark\Domain\Repository\DeclarationRepository;
use NetThinks\NtAimark\Service\AiMarkSettingsFactory;
use NetThinks\NtAimark\Service\DisclosureRuleService;
use NetThinks\NtAimark\Service\LabelRenderService;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Renders the disclosure badge for a file, or nothing when the rules say the
 * file is not to be labelled.
 *
 * <nt:aiLabel file="{file}" position="bottom-right" size="medium" />
 */
final class AiLabelViewHelper extends AbstractLabelViewHelper
{
    protected $escapeOutput = false;

    public function __construct(
        DeclarationRepository $declarationRepository,
        DisclosureRuleService $disclosureRuleService,
        AiMarkSettingsFactory $settingsFactory,
        private readonly LabelRenderService $labelRenderService,
    ) {
        parent::__construct($declarationRepository, $disclosureRuleService, $settingsFactory);
    }

    public function initializeArguments(): void
    {
        $this->registerArgument('file', 'object', 'FAL file or file reference', true);
        $this->registerArgument('position', 'string', 'Badge position within the image', false, 'bottom-right');
        $this->registerArgument('size', 'string', 'Badge size', false, 'medium');
        $this->registerArgument('showDetails', 'bool', 'Offer the expandable detail panel. Null follows the site setting.', false, null);
        $this->registerArgument('showTextLabel', 'bool', 'Wording beside the icon. Null follows the site setting; false is the one to use for thumbnails, where it no longer fits.', false, null);
    }

    public function render(): string
    {
        $file = $this->arguments['file'];
        $result = $this->decide($file);

        if ($result === null) {
            return '';
        }

        [$declaration, $decision] = $result;

        return $this->labelRenderService->renderBadge(
            $declaration,
            $decision,
            $this->request(),
            (string) $this->arguments['position'],
            (string) $this->arguments['size'],
            $this->arguments['showDetails'] ?? $this->settings()->showDetails,
            $file instanceof FileInterface ? $file : null,
            showTextLabel: $this->arguments['showTextLabel'] ?? $this->settings()->showTextLabel,
        );
    }
}
