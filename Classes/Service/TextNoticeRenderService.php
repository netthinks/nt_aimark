<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\Enum\TextStatus;
use NetThinks\NtAimark\Domain\Model\TextLabelDecision;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Renders the disclosure notice for a text.
 *
 * Unlike the media badge this is plain running text, not an icon: a sentence
 * is what makes a text disclosure understandable at first contact.
 */
final readonly class TextNoticeRenderService
{
    private const LL = 'LLL:EXT:nt_aimark/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private ViewFactoryInterface $viewFactory,
        private AssetCollector $assetCollector,
    ) {}

    public function render(TextLabelDecision $decision, ?ServerRequestInterface $request = null): string
    {
        if (!$decision->shouldLabel) {
            return '';
        }

        $this->assetCollector->addStyleSheet(
            'ntAimark',
            'EXT:nt_aimark/Resources/Public/Css/aimark.css',
        );

        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:nt_aimark/Resources/Private/Templates/'],
            partialRootPaths: ['EXT:nt_aimark/Resources/Private/Partials/'],
            layoutRootPaths: ['EXT:nt_aimark/Resources/Private/Layouts/'],
            request: $request,
        ));

        $view->assignMultiple([
            'headingKey' => self::LL . 'text.notice.heading',
            'noticeKey' => $this->noticeKey($decision->status),
            'status' => $decision->status->name,
        ]);

        return trim($view->render('Label/TextNotice'));
    }

    private function noticeKey(TextStatus $status): string
    {
        return self::LL . 'text.notice.' . match ($status) {
            TextStatus::AiGenerated => 'generated',
            default => 'draftRevised',
        };
    }
}
