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
        $this->registerArgument('showDetails', 'bool', 'Render the expandable detail panel', false, true);
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
            (bool) $this->arguments['showDetails'],
            $file instanceof FileInterface ? $file : null,
        );
    }
}
