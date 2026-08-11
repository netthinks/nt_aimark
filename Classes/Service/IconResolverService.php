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

    /**
     * CSS properties that mean the same thing as an SVG presentation
     * attribute. The list is deliberately short: a property not on it stops
     * the rewrite instead of being guessed at.
     *
     * @var list<string>
     */
    /**
     * Die tatsächlich bezeichnete Fläche je Variante, in Einheiten des
     * mitgelieferten viewBox.
     *
     * Die offiziellen Dateien setzen die Zeichnung mit reichlich Rand auf eine
     * größere Leinwand: Beim Zeichen „AI GENERATED" belegt sie 1384 × 266 von
     * 1790 × 567 Einheiten, also nur 47 % der Höhe. Eingebettet ergibt das
     * eine Plakette, die zur Hälfte aus Leerraum besteht.
     *
     * Die Werte stammen aus `getBBox()` über die ausgelieferten Dateien, nicht
     * aus Schätzung. Die Grafik selbst bleibt unangetastet — es wird nur der
     * sichtbare Ausschnitt enger gefasst, was den Vorgaben zur Verwendung
     * nicht widerspricht: Nachzeichnen, Umfärben und Übersetzen wären
     * unzulässig, ein engerer Rahmen ist keines davon.
     *
     * @var array<string, array{0: float, 1: float, 2: float, 3: float}>
     */
    private const ARTWORK_BOUNDS = [
        'ai-basic' => [89.28, 100.72, 365.49, 365.49],
        'ai-generated' => [207.3, 144.36, 1384.24, 266.41],
        'ai-modified' => [231.11, 144.36, 1230.56, 266.41],
    ];

    /**
     * Luft um die Zeichnung, als Anteil ihrer Höhe. Ohne sie stößt das Zeichen
     * an den Rand der Plakette.
     */
    private const ARTWORK_MARGIN = 0.12;

    private const PRESENTATION_ATTRIBUTES = [
        'fill', 'fill-rule', 'fill-opacity',
        'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray',
        'opacity', 'color', 'clip-rule', 'isolation', 'mix-blend-mode',
    ];

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

        $prepared = $this->tightenViewBox($this->prepareForInlineUse($svg), $fileName);

        // Sanitising can empty the file out entirely. Falling back to the text
        // label is the right outcome then, not emitting a broken fragment.
        return str_contains($prepared, '<svg') ? $prepared : null;
    }

    /**
     * Narrows the visible area to the drawing itself.
     *
     * The official files place the mark on a canvas with a wide margin — for
     * "AI GENERATED" the drawing covers 47 % of the height. Embedded as it
     * comes, the badge is half empty space, and the mark next to it looks
     * smaller than it is.
     *
     * Only the frame changes. The artwork is not redrawn, recoloured or
     * altered in any way; a file whose bounds are not known keeps its original
     * viewBox.
     */
    private function tightenViewBox(string $svg, string $fileName): string
    {
        $variant = null;
        foreach (array_keys(self::ARTWORK_BOUNDS) as $kandidat) {
            if (str_starts_with($fileName, $kandidat . '-')) {
                $variant = $kandidat;
                break;
            }
        }

        if ($variant === null || !preg_match('/<svg\b[^>]*\bviewBox="([^"]+)"/i', $svg)) {
            return $svg;
        }

        [$x, $y, $breite, $hoehe] = self::ARTWORK_BOUNDS[$variant];
        $luft = $hoehe * self::ARTWORK_MARGIN;

        $viewBox = sprintf(
            '%s %s %s %s',
            $this->zahl($x - $luft),
            $this->zahl($y - $luft),
            $this->zahl($breite + 2 * $luft),
            $this->zahl($hoehe + 2 * $luft),
        );

        return (string) preg_replace(
            '/(<svg\b[^>]*\bviewBox=")[^"]+(")/i',
            '${1}' . $viewBox . '${2}',
            $svg,
            1,
        );
    }

    private function zahl(float $wert): string
    {
        return rtrim(rtrim(number_format($wert, 2, '.', ''), '0'), '.');
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

        return trim($this->isolateInternalStyling($svg));
    }

    /**
     * Removes the icon's dependency on an inline stylesheet.
     *
     * The official files carry their colours in a `<style>` block inside the
     * SVG. That does not survive contact with a Content Security Policy: as
     * soon as `style-src-elem` names a nonce — which TYPO3 v14 does by default
     * — the browser drops `'unsafe-inline'` and refuses the block. Nothing
     * fails visibly; the paths simply fall back to the initial fill and the
     * official mark renders as a solid black shape. A wrong label, silently,
     * on exactly the hardened installations one would want to get this right.
     *
     * The declarations are therefore moved onto the elements as presentation
     * attributes, which no policy blocks. As a side effect the shared generic
     * class names (all twelve files declare `.cls-1`/`.cls-2`) disappear along
     * with the collision they would cause between two icons on one page.
     *
     * If anything about the file is not understood, the markup is left as it
     * was and only the ids are made unique — a degraded icon still beats none.
     */
    private function isolateInternalStyling(string $svg): string
    {
        if (str_contains($svg, '<style')) {
            $svg = $this->inlineStyleDeclarations($svg) ?? $this->uniquifyClassNames($svg);
        }

        return $this->uniquifyIds($svg);
    }

    private function inlineStyleDeclarations(string $svg): ?string
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            if (!$document->loadXML($svg, LIBXML_NONET)) {
                return null;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new \DOMXPath($document);
        $styleElements = $xpath->query('//*[local-name()="style"]');

        if (!$styleElements instanceof \DOMNodeList || $styleElements->length === 0) {
            return null;
        }

        $rules = [];

        foreach ($styleElements as $style) {
            if (!$style instanceof \DOMElement) {
                continue;
            }

            $parsed = $this->parseClassRules($style->textContent);

            if ($parsed === null) {
                // Something beyond plain class rules — leave the file alone
                // rather than half-applying it.
                return null;
            }

            foreach ($parsed as $class => $declarations) {
                $rules[$class] = array_merge($rules[$class] ?? [], $declarations);
            }
        }

        if ($rules === []) {
            return null;
        }

        $elements = $xpath->query('//*[@class]');

        if ($elements instanceof \DOMNodeList) {
            foreach ($elements as $element) {
                if (!$element instanceof \DOMElement) {
                    continue;
                }

                $remaining = [];

                foreach (preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [] as $class) {
                    if ($class === '') {
                        continue;
                    }

                    if (!isset($rules[$class])) {
                        // Not from the icon's own stylesheet — the class this
                        // service puts on the root element, for one. Dropping
                        // it would take the sizing rules with it.
                        $remaining[] = $class;

                        continue;
                    }

                    foreach ($rules[$class] as $property => $value) {
                        // CSS outranks presentation attributes, so a rule
                        // replaces an attribute of the same name.
                        $element->setAttribute($property, $value);
                    }
                }

                if ($remaining === []) {
                    $element->removeAttribute('class');
                } else {
                    $element->setAttribute('class', implode(' ', $remaining));
                }
            }
        }

        foreach (iterator_to_array($styleElements) as $style) {
            if ($style instanceof \DOMNode) {
                $style->parentNode?->removeChild($style);
            }
        }

        $result = $document->saveXML($document->documentElement);

        return is_string($result) ? $result : null;
    }

    /**
     * Only plain single-class selectors are understood; anything else returns
     * null so the caller can leave the file untouched.
     *
     * @return array<string, array<string, string>>|null
     */
    private function parseClassRules(string $css): ?array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $rules = [];
        $offset = 0;

        while (preg_match('/([^{}]+)\{([^{}]*)\}/s', $css, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $offset = $match[0][1] + strlen($match[0][0]);
            $selectors = array_map('trim', explode(',', $match[1][0]));

            foreach ($selectors as $selector) {
                if (preg_match('/^\.([A-Za-z_][\w-]*)$/', $selector, $name) !== 1) {
                    return null;
                }

                foreach (explode(';', $match[2][0]) as $declaration) {
                    if (trim($declaration) === '') {
                        continue;
                    }

                    $parts = explode(':', $declaration, 2);

                    if (count($parts) !== 2) {
                        return null;
                    }

                    $property = strtolower(trim($parts[0]));
                    $value = trim($parts[1]);

                    // Presentation attributes cover paint and geometry only.
                    // Anything else would silently change meaning as an
                    // attribute, so it stops the whole rewrite.
                    if (!in_array($property, self::PRESENTATION_ATTRIBUTES, true) || $value === '') {
                        return null;
                    }

                    $rules[$name[1]][$property] = $value;
                }
            }
        }

        return $rules;
    }

    /**
     * Fallback when the stylesheet cannot be inlined: at least keep two icons
     * on one page from restyling each other.
     */
    private function uniquifyClassNames(string $svg): string
    {
        return (string) preg_replace(
            '/(?<![\w-])(cls-\d+)(?![\w-])/',
            '$1-' . substr(hash('xxh128', $svg), 0, 8),
            $svg,
        );
    }

    /**
     * All twelve official files use `id="Calque_1"`; duplicated ids in one
     * document are invalid markup.
     */
    private function uniquifyIds(string $svg): string
    {
        if (!str_contains($svg, 'id="')) {
            return $svg;
        }

        $suffix = '-' . substr(hash('xxh128', $svg), 0, 8);

        return (string) preg_replace_callback(
            '/\bid="([^"]+)"/',
            static fn(array $m): string => 'id="' . $m[1] . $suffix . '"',
            $svg,
        );
    }
}
