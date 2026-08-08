<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\Enum\IconVariant;
use TYPO3\CMS\Core\Resource\Security\SvgSanitizer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Loads the official EU labelling icons for inline embedding.
 *
 * The icon files are not shipped with the extension. If they are absent the
 * service returns null and the caller falls back to a text label — a missing
 * download must never produce a fatal error or an empty image.
 *
 * @see Resources/Public/Icons/Eu/README.md
 */
final class IconResolverService
{
    public const ICON_DIRECTORY = 'EXT:nt_aimark/Resources/Public/Icons/Eu/';

    /** @var array<string, string|null> */
    private array $runtimeCache = [];

    /**
     * The directory is injectable so tests can point at a fixture directory —
     * EXT: paths are not resolvable without a registered package.
     */
    public function __construct(
        private readonly string $iconDirectory = self::ICON_DIRECTORY,
        private readonly ?SvgSanitizer $svgSanitizer = null,
    ) {}

    /**
     * The icon markup, ready to be placed inside the badge element, or null
     * when the file was never downloaded.
     */
    public function inlineSvg(IconVariant $variant, bool $white = false, bool $transparent = false): ?string
    {
        $fileName = $variant->fileName($white, $transparent);

        if ($fileName === '') {
            return null;
        }

        return $this->runtimeCache[$fileName] ??= $this->loadSvg($fileName);
    }

    public function isAvailable(IconVariant $variant, bool $white = false, bool $transparent = false): bool
    {
        return $this->inlineSvg($variant, $white, $transparent) !== null;
    }

    /**
     * Variants the operator still has to download — used by the system status
     * report to explain why labels currently render as text.
     *
     * @return list<IconVariant>
     */
    public function missingVariants(): array
    {
        $missing = [];

        foreach ([IconVariant::Basic, IconVariant::Generated, IconVariant::Modified] as $variant) {
            if (!$this->isAvailable($variant) || !$this->isAvailable($variant, white: true)) {
                $missing[] = $variant;
            }
        }

        return $missing;
    }

    private function loadSvg(string $fileName): ?string
    {
        $path = $this->iconDirectory . $fileName;
        $absolutePath = str_starts_with($path, 'EXT:') ? GeneralUtility::getFileAbsFileName($path) : $path;

        if ($absolutePath === '' || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }

        $svg = file_get_contents($absolutePath);

        if ($svg === false || !str_contains($svg, '<svg')) {
            return null;
        }

        $prepared = $this->prepareForInlineUse($svg);

        // Sanitising can empty the file out entirely. Falling back to the text
        // label is the right outcome then, not emitting a broken fragment.
        return str_contains($prepared, '<svg') ? $prepared : null;
    }

    /**
     * Strips what must not appear inside an HTML document and hides the graphic
     * from assistive technology — the surrounding badge carries the text
     * alternative, so announcing the icon again would duplicate it.
     *
     * The markup is embedded into the page unescaped, which makes this file the
     * one place where a tampered download would become script execution. The
     * icons arrive by manual download rather than through TYPO3's upload
     * checks, so they are sanitised here before anything else happens to them.
     */
    private function prepareForInlineUse(string $svg): string
    {
        $sanitizer = $this->svgSanitizer ?? GeneralUtility::makeInstance(SvgSanitizer::class);
        $svg = $sanitizer->sanitizeContent($svg);

        $svg = (string) preg_replace('/<\?xml.*?\?>/s', '', $svg);
        $svg = (string) preg_replace('/<!DOCTYPE.*?>/s', '', $svg);
        $svg = (string) preg_replace('/<!--.*?-->/s', '', $svg);

        // Only the root element is rewritten; inner class attributes belong to
        // the icon's own styling and must survive.
        $svg = (string) preg_replace_callback(
            '/<svg\b[^>]*>/i',
            static function (array $match): string {
                $tag = preg_replace('/\s(?:aria-hidden|focusable|class)="[^"]*"/i', '', $match[0]) ?? $match[0];

                return (string) preg_replace(
                    '/<svg\b/i',
                    '<svg aria-hidden="true" focusable="false" class="nt-aimark__icon"',
                    $tag,
                    1,
                );
            },
            $svg,
            1,
        );

        return trim($svg);
    }
}
