<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\IconVariant;
use NetThinks\NtAimark\Domain\Model\AiDeclaration;
use NetThinks\NtAimark\Domain\Model\BadgeContrast;
use NetThinks\NtAimark\Domain\Model\LabelDecision;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Turns a labelling decision into markup.
 *
 * Everything visitor-facing goes through Fluid partials so integrators can
 * override the markup without touching PHP.
 */
final class LabelRenderService
{
    private const LL = 'LLL:EXT:nt_aimark/Resources/Private/Language/locallang.xlf:';

    /**
     * Detail panels need ids that are unique within the document. Record uids
     * are not enough — the same image can appear twice on one page.
     */
    private int $instanceCounter = 0;

    public function __construct(
        private readonly IconResolverService $iconResolver,
        private readonly ViewFactoryInterface $viewFactory,
        private readonly AssetCollector $assetCollector,
        private readonly BadgeContrastService $badgeContrastService,
    ) {}

    public function renderBadge(
        AiDeclaration $declaration,
        LabelDecision $decision,
        ?ServerRequestInterface $request = null,
        string $position = 'bottom-right',
        string $size = 'medium',
        bool $showDetails = true,
        ?FileInterface $file = null,
        string $imageMarkup = '',
        bool $showTextLabel = true,
    ): string {
        if (!$decision->shouldLabel) {
            return '';
        }

        $position = $this->sanitiseModifier($position, 'bottom-right');

        $this->assetCollector->addStyleSheet(
            'ntAimark',
            'EXT:nt_aimark/Resources/Public/Css/aimark.css',
        );

        $detailRows = $this->detailRows($decision);
        $withDetails = $showDetails && $detailRows !== [];

        if ($withDetails) {
            $this->assetCollector->addJavaScript(
                'ntAimarkDetails',
                'EXT:nt_aimark/Resources/Public/JavaScript/aimark-details.js',
                ['type' => 'module'],
            );
        }

        $contrast = $file !== null
            ? $this->badgeContrastService->resolve($file, $position)
            : BadgeContrast::guaranteed();

        $icon = $this->iconResolver->inlineSvg($decision->iconVariant, white: $contrast->useWhiteIcon);

        // The white variant may simply not have been downloaded. Rather than
        // dropping to a text label, use the black one — but then the plate has
        // to come back, otherwise it would sit dark on dark.
        if ($icon === null && $contrast->useWhiteIcon) {
            $icon = $this->iconResolver->inlineSvg($decision->iconVariant);
            $contrast = BadgeContrast::guaranteed();
        }

        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:nt_aimark/Resources/Private/Templates/'],
            partialRootPaths: ['EXT:nt_aimark/Resources/Private/Partials/'],
            layoutRootPaths: ['EXT:nt_aimark/Resources/Private/Layouts/'],
            request: $request,
        ));

        $view->assignMultiple([
            'icon' => $icon,
            // Without the downloaded icon files the badge degrades to a text
            // label. It must stay perceivable, not disappear.
            'fallbackTextKey' => $this->fallbackTextKey($decision->iconVariant),
            'ariaLabelKey' => $this->ariaLabelKey($declaration->status, $decision->iconVariant),
            'labelText' => $decision->labelText,
            'position' => $position,
            'size' => $this->sanitiseModifier($size, 'medium'),
            'contrast' => $contrast->cssModifier(),
            'iconColour' => $contrast->useWhiteIcon ? 'white' : 'dark',
            'status' => $declaration->status->name,
            'variant' => $decision->iconVariant->value,
            'detailId' => 'aimark-detail-' . $declaration->recordUid . '-' . ++$this->instanceCounter,
            'detailRows' => $detailRows,
            'showDetails' => $withDetails,
            // When the image markup comes along, the badge is placed inside a
            // frame around it. Without that frame it would be positioned
            // against the whole figure — which also holds the toggle and the
            // detail panel — and would come to rest below the picture instead
            // of on it.
            'imageMarkup' => $imageMarkup,
            // The official icons carry an English wordmark and must not be
            // redrawn — so the meaning travels in the text beside them, in
            // the language of the site. An entry on the file wins over it.
            // Suppressed when the icon is absent, because the fallback below
            // already says the same thing.
            'standardTextKey' => $showTextLabel && $icon !== null && $decision->labelText === ''
                ? $this->fallbackTextKey($decision->iconVariant)
                : '',
        ]);

        return trim($view->render('Label/Badge'));
    }

    /**
     * Rows for the expandable panel, already reduced to what may be shown.
     *
     * @return list<array{labelKey: string, value: string|int, type: string}>
     */
    private function detailRows(LabelDecision $decision): array
    {
        $rows = [];

        if (isset($decision->detailPayload['system'])) {
            $rows[] = [
                'labelKey' => self::LL . 'detail.system',
                'value' => $decision->detailPayload['system'],
                'type' => 'text',
            ];
        }
        if (isset($decision->detailPayload['createdAt'])) {
            $rows[] = [
                'labelKey' => self::LL . 'detail.createdAt',
                'value' => $decision->detailPayload['createdAt'],
                'type' => 'date',
            ];
        }

        return $rows;
    }

    private function ariaLabelKey(AiStatus $status, IconVariant $variant): string
    {
        $suffix = match (true) {
            $status === AiStatus::Generated, $variant === IconVariant::Generated => 'generated',
            $status === AiStatus::Modified, $variant === IconVariant::Modified => 'modified',
            default => 'basic',
        };

        return self::LL . 'badge.aria.' . $suffix;
    }

    private function fallbackTextKey(IconVariant $variant): string
    {
        return self::LL . 'badge.text.' . match ($variant) {
            IconVariant::Generated => 'generated',
            IconVariant::Modified => 'modified',
            default => 'basic',
        };
    }

    /**
     * Position and size end up in a CSS class name, so only known values pass.
     */
    private function sanitiseModifier(string $value, string $fallback): string
    {
        return preg_match('/^[a-z]+(-[a-z]+)?$/', $value) === 1 ? $value : $fallback;
    }
}
