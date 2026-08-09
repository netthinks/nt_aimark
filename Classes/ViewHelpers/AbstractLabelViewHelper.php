<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\ViewHelpers;

use NetThinks\NtAimark\Domain\Model\AiDeclaration;
use NetThinks\NtAimark\Domain\Model\AiMarkSettings;
use NetThinks\NtAimark\Domain\Model\LabelDecision;
use NetThinks\NtAimark\Domain\Repository\DeclarationRepository;
use NetThinks\NtAimark\Service\AiMarkSettingsFactory;
use NetThinks\NtAimark\Service\DisclosureRuleService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Shared plumbing for the labelling ViewHelpers: get from a file to a decision.
 */
abstract class AbstractLabelViewHelper extends AbstractViewHelper
{
    public function __construct(
        protected readonly DeclarationRepository $declarationRepository,
        protected readonly DisclosureRuleService $disclosureRuleService,
        protected readonly AiMarkSettingsFactory $settingsFactory,
    ) {}

    /**
     * @return array{AiDeclaration, LabelDecision}|null Null when the argument is not a file
     */
    protected function decide(mixed $file): ?array
    {
        if (!$file instanceof FileInterface) {
            return null;
        }

        $declaration = $this->declarationRepository->forFile($file);
        $decision = $this->disclosureRuleService->resolve($declaration, $this->settings());

        return [$declaration, $decision];
    }

    protected function settings(): AiMarkSettings
    {
        return $this->settingsFactory->fromRequest($this->request());
    }

    /**
     * The request is absent in some rendering contexts (e.g. a standalone view
     * in a CLI run); the settings then fall back to their defaults.
     */
    protected function request(): ?ServerRequestInterface
    {
        $context = $this->renderingContext;

        if ($context === null || !$context->hasAttribute(ServerRequestInterface::class)) {
            return null;
        }

        $request = $context->getAttribute(ServerRequestInterface::class);

        return $request instanceof ServerRequestInterface ? $request : null;
    }
}
