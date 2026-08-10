<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\ViewHelpers;

use NetThinks\NtAimark\Domain\Model\TextDeclaration;
use NetThinks\NtAimark\Service\TextDisclosureRuleService;
use NetThinks\NtAimark\Service\TextNoticeRenderService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders the disclosure notice for a text record, or nothing when the rules
 * say the text is not to be disclosed.
 *
 * <nt:textNotice record="{data}" table="tt_content" />
 */
final class TextNoticeViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function __construct(
        private readonly TextDisclosureRuleService $textDisclosureRuleService,
        private readonly TextNoticeRenderService $textNoticeRenderService,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('record', 'array', 'The record row, e.g. {data}', true);
        $this->registerArgument('table', 'string', 'Table the record belongs to', false, 'tt_content');
    }

    public function render(): string
    {
        $record = $this->arguments['record'];

        if (!is_array($record)) {
            return '';
        }

        $declaration = TextDeclaration::fromRecord($record, (string) $this->arguments['table']);
        $decision = $this->textDisclosureRuleService->resolve($declaration);

        return $this->textNoticeRenderService->render($decision, $this->request());
    }

    private function request(): ?ServerRequestInterface
    {
        $context = $this->renderingContext;

        if ($context === null || !$context->hasAttribute(ServerRequestInterface::class)) {
            return null;
        }

        $request = $context->getAttribute(ServerRequestInterface::class);

        return $request instanceof ServerRequestInterface ? $request : null;
    }
}
