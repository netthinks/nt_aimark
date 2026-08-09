<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\ViewHelpers;

use NetThinks\NtAimark\Domain\Repository\DeclarationRepository;
use NetThinks\NtAimark\Service\AiMarkSettingsFactory;
use NetThinks\NtAimark\Service\DisclosureRuleService;
use NetThinks\NtAimark\Service\LabelRenderService;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Wraps image markup in a figure carrying the disclosure badge.
 *
 * <nt:aiFigure file="{file}">
 *     <f:image image="{file}" width="800" />
 * </nt:aiFigure>
 *
 * When the file is not to be labelled the children are returned untouched, so
 * the ViewHelper can be applied unconditionally.
 */
final class AiFigureViewHelper extends AbstractLabelViewHelper
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
        $this->registerArgument('class', 'string', 'Additional CSS classes for the figure', false, '');
    }

    public function render(): string
    {
        $content = (string) $this->renderChildren();
        $file = $this->arguments['file'];
        $result = $this->decide($file);

        if ($result === null) {
            return $content;
        }

        [$declaration, $decision] = $result;

        $badge = $this->labelRenderService->renderBadge(
            $declaration,
            $decision,
            $this->request(),
            (string) $this->arguments['position'],
            (string) $this->arguments['size'],
            (bool) $this->arguments['showDetails'],
            $file instanceof FileInterface ? $file : null,
            $content,
            $this->settings()->showTextLabel,
        );

        if ($badge === '') {
            return $content;
        }

        $classes = trim('nt-aimark ' . (string) $this->arguments['class']);

        // The image markup is already inside $badge — it was handed over so the
        // badge could be framed around it.
        return sprintf(
            '<figure class="%s" data-ai-status="%s">%s</figure>',
            htmlspecialchars($classes, ENT_QUOTES | ENT_HTML5),
            htmlspecialchars($decision->iconVariant->value, ENT_QUOTES | ENT_HTML5),
            $badge,
        );
    }
}
