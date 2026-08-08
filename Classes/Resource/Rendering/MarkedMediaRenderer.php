<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Resource\Rendering;

use NetThinks\NtAimark\Domain\Repository\DeclarationRepository;
use NetThinks\NtAimark\Service\AiMarkSettingsFactory;
use NetThinks\NtAimark\Service\DisclosureRuleService;
use NetThinks\NtAimark\Service\LabelRenderService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Rendering\FileRendererInterface;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Adds the disclosure label to audio and video output without any template
 * change, by wrapping whatever the core renderer produced.
 *
 * Deliberately does NOT claim images. TYPO3 ships no image file renderer —
 * MediaViewHelper falls back to its own private rendering when no renderer
 * matches. Claiming images here would mean reimplementing that fallback
 * (crop variants, focus area, loading, decoding, alt and title handling) and
 * maintaining the copy against every core release. Images are covered by the
 * ViewHelpers instead.
 */
final class MarkedMediaRenderer implements FileRendererInterface
{
    /**
     * Just above the core renderers, which all sit at 1.
     */
    public function getPriority(): int
    {
        return 10;
    }

    public function canRender(FileInterface $file): bool
    {
        $request = $this->request();

        if ($request === null) {
            return false;
        }

        if (!$this->settingsFactory()->fromRequest($request)->useFileRenderer) {
            return false;
        }

        if ($this->delegateFor($file) === null) {
            return false;
        }

        return $this->decision($file, $request);
    }

    /**
     * @param int|string $width
     * @param int|string $height
     * @param array<string, mixed> $options
     */
    public function render(FileInterface $file, $width, $height, array $options = []): string
    {
        $delegate = $this->delegateFor($file);
        $request = $this->request();

        if ($delegate === null) {
            return '';
        }

        $media = $delegate->render($file, $width, $height, $options);

        if ($request === null) {
            return $media;
        }

        $declaration = GeneralUtility::makeInstance(DeclarationRepository::class)->forFile($file);
        $decision = GeneralUtility::makeInstance(DisclosureRuleService::class)->resolve(
            $declaration,
            $this->settingsFactory()->fromRequest($request),
        );

        $settings = $this->settingsFactory()->fromRequest($request);

        $badge = GeneralUtility::makeInstance(LabelRenderService::class)->renderBadge(
            $declaration,
            $decision,
            $request,
            $settings->badgePosition,
            $settings->badgeSize,
            $settings->showDetails,
            $file,
        );

        if ($badge === '') {
            return $media;
        }

        return sprintf(
            '<figure class="nt-aimark" data-ai-status="%s">%s%s</figure>',
            htmlspecialchars($decision->iconVariant->value, ENT_QUOTES | ENT_HTML5),
            $media,
            $badge,
        );
    }

    /**
     * The renderer that would have run if this one did not exist.
     *
     * Skips itself, otherwise the registry would hand back this very instance
     * and the delegation would loop.
     */
    private function delegateFor(FileInterface $file): ?FileRendererInterface
    {
        foreach (GeneralUtility::makeInstance(RendererRegistry::class)->getRendererInstances() as $renderer) {
            if ($renderer instanceof self) {
                continue;
            }

            if ($renderer->canRender($file)) {
                return $renderer;
            }
        }

        return null;
    }

    private function decision(FileInterface $file, ServerRequestInterface $request): bool
    {
        $declaration = GeneralUtility::makeInstance(DeclarationRepository::class)->forFile($file);

        return GeneralUtility::makeInstance(DisclosureRuleService::class)->resolve(
            $declaration,
            $this->settingsFactory()->fromRequest($request),
        )->shouldLabel;
    }

    private function settingsFactory(): AiMarkSettingsFactory
    {
        return GeneralUtility::makeInstance(AiMarkSettingsFactory::class);
    }

    /**
     * File renderers are instantiated by the registry without the container, so
     * the request comes from the global rather than from constructor injection.
     */
    private function request(): ?ServerRequestInterface
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        return $request instanceof ServerRequestInterface ? $request : null;
    }
}
