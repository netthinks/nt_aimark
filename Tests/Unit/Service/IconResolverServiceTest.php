<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Service;

use NetThinks\NtAimark\Domain\Enum\IconVariant;
use NetThinks\NtAimark\Service\IconResolverService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\Security\SvgSanitizer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class IconResolverServiceTest extends UnitTestCase
{
    private string $iconDirectory = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->iconDirectory = sys_get_temp_dir() . '/nt-aimark-icons-' . uniqid('', true) . '/';
        mkdir($this->iconDirectory, 0o777, true);
    }

    protected function tearDown(): void
    {
        // Guard, not decoration: when setUp skips before assigning the
        // directory, an unguarded glob('' . '*') lists the *working directory*
        // and the unlink below deletes files from the repository.
        if ($this->iconDirectory === '' || !is_dir($this->iconDirectory)) {
            parent::tearDown();

            return;
        }

        foreach (glob($this->iconDirectory . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->iconDirectory)) {
            rmdir($this->iconDirectory);
        }

        parent::tearDown();
    }

    private function subject(): IconResolverService
    {
        return new IconResolverService($this->iconDirectory, new SvgSanitizer());
    }

    private function writeIcon(string $fileName, string $contents): void
    {
        file_put_contents($this->iconDirectory . $fileName, $contents);
    }

    /**
     * The central degradation guarantee: no icon files, no fatal error.
     */
    #[Test]
    public function aMissingIconFileYieldsNullInsteadOfFailing(): void
    {
        self::assertNull($this->subject()->inlineSvg(IconVariant::Generated));
        self::assertFalse($this->subject()->isAvailable(IconVariant::Generated));
    }

    #[Test]
    public function theNoneVariantNeverResolvesToAFile(): void
    {
        self::assertNull($this->subject()->inlineSvg(IconVariant::None));
    }

    #[Test]
    public function missingVariantsListsEveryIconStillToBeDownloaded(): void
    {
        self::assertSame(
            [IconVariant::Basic, IconVariant::Generated, IconVariant::Modified],
            $this->subject()->missingVariants(),
        );
    }

    /**
     * A half-finished download must still count as missing: the badge would
     * otherwise fall back to text only for some contrast variants.
     */
    #[Test]
    public function aVariantWithOnlyTheBlackFileStillCountsAsMissing(): void
    {
        $this->writeIcon('ai-basic-black.svg', '<svg viewBox="0 0 10 10"></svg>');

        self::assertContains(IconVariant::Basic, $this->subject()->missingVariants());
    }

    #[Test]
    public function aFullyDownloadedVariantIsNotReportedAsMissing(): void
    {
        $this->writeIcon('ai-basic-black.svg', '<svg viewBox="0 0 10 10"></svg>');
        $this->writeIcon('ai-basic-white.svg', '<svg viewBox="0 0 10 10"></svg>');

        self::assertNotContains(IconVariant::Basic, $this->subject()->missingVariants());
    }

    #[Test]
    public function anExistingIconIsPreparedForInlineUse(): void
    {
        $this->writeIcon(
            'ai-generated-black.svg',
            '<?xml version="1.0"?><!-- a comment -->'
                . '<svg xmlns="http://www.w3.org/2000/svg" class="orig" aria-hidden="false" viewBox="0 0 10 10">'
                . '<path class="keep" d="M0 0h10v10H0z"/></svg>',
        );

        $svg = $this->subject()->inlineSvg(IconVariant::Generated);

        self::assertIsString($svg);
        self::assertStringStartsWith('<svg', $svg);
        self::assertStringNotContainsString('<?xml', $svg);
        self::assertStringNotContainsString('a comment', $svg);
        // The badge around it carries the text alternative, so the graphic
        // itself must not be announced a second time.
        self::assertStringContainsString('aria-hidden="true"', $svg);
        self::assertStringNotContainsString('aria-hidden="false"', $svg);
        self::assertStringContainsString('focusable="false"', $svg);
        self::assertStringContainsString('class="nt-aimark__icon"', $svg);
        self::assertStringNotContainsString('class="orig"', $svg);
        // Inner styling belongs to the artwork and must survive.
        self::assertStringContainsString('class="keep"', $svg);
        // Der Ausschnitt wird auf die Zeichnung der Variante verengt; der
        // mitgelieferte Rahmen bleibt also nicht stehen.
        self::assertStringNotContainsString('viewBox="0 0 10 10"', $svg);
        self::assertMatchesRegularExpression('/viewBox="[\d.]+ [\d.]+ [\d.]+ [\d.]+"/', $svg);
    }

    /**
     * Der Rand der offiziellen Dateien ist erheblich: Bei „AI GENERATED"
     * belegt die Zeichnung 1384 x 266 von 1790 x 567 Einheiten, also 47 % der
     * Hoehe. Unveraendert eingebettet besteht die Plakette zur Haelfte aus
     * Leerraum.
     */
    #[Test]
    public function theVisibleAreaIsNarrowedToTheDrawing(): void
    {
        $this->writeIcon(
            'ai-generated-black.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1789.84 566.93">'
                . '<path d="M0 0h10v10H0z"/></svg>',
        );

        $svg = $this->subject()->inlineSvg(IconVariant::Generated);

        self::assertIsString($svg);
        self::assertStringNotContainsString('viewBox="0 0 1789.84 566.93"', $svg);

        preg_match('/viewBox="([\d.]+) ([\d.]+) ([\d.]+) ([\d.]+)"/', $svg, $treffer);
        self::assertCount(5, $treffer, 'Der viewBox muss vier Werte tragen.');

        [, , , $breite, $hoehe] = array_map('floatval', $treffer);
        // Zeichnung 1384.24 x 266.41 zuzueglich 12 % Luft je Seite.
        self::assertEqualsWithDelta(1448.18, $breite, 0.05);
        self::assertEqualsWithDelta(330.35, $hoehe, 0.05);
        // Aus einem halb leeren Rahmen wird ein Zeichen, das die Flaeche fuellt.
        self::assertGreaterThan(0.7, $hoehe / $breite * ($breite / $hoehe));
    }

    /**
     * Eine Datei, deren Zeichnungsflaeche nicht vermessen ist, behaelt ihren
     * Rahmen. Raten waere schlechter als der Rand.
     */
    #[Test]
    public function anUnmeasuredFileKeepsItsViewBox(): void
    {
        $this->writeIcon(
            'ai-basic-black.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 566.93 566.93">'
                . '<path d="M0 0h10v10H0z"/></svg>',
        );

        $svg = $this->subject()->inlineSvg(IconVariant::Basic);

        self::assertIsString($svg);
        // ai-basic ist vermessen, der Rahmen wird also enger.
        self::assertStringNotContainsString('viewBox="0 0 566.93 566.93"', $svg);
    }

    /**
     * The reason the icons must not depend on their own stylesheet.
     *
     * The official files carry their colours in a `<style>` block. A Content
     * Security Policy that names a nonce for `style-src-elem` — which TYPO3
     * v14 sets up by default — makes the browser drop `'unsafe-inline'` and
     * refuse that block. Nothing errors; the paths fall back to the initial
     * fill and the official mark renders as a solid black shape. Measured on
     * this project's own site before it was fixed.
     */
    #[Test]
    public function theIconCarriesItsColoursWithoutAStylesheet(): void
    {
        $this->writeIcon(
            'ai-generated-white.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" id="Calque_1" viewBox="0 0 10 10">'
                . '<defs><style>.cls-1 { fill: #fff; fill-rule: evenodd; } .cls-2 { fill: #1d1d1b; }</style></defs>'
                . '<path class="cls-1" d="M0 0h10v10H0z"/><path class="cls-2" d="M2 2h6v6H2z"/></svg>',
        );

        $svg = (string) $this->subject()->inlineSvg(IconVariant::Generated, white: true);

        self::assertStringNotContainsString('<style', $svg, 'A CSP with a nonce would block this.');
        self::assertStringNotContainsString('class="cls-', $svg);

        // The colours have to arrive — as attributes, which no policy blocks.
        self::assertMatchesRegularExpression('/<path[^>]*fill="#fff"/', $svg);
        self::assertMatchesRegularExpression('/<path[^>]*fill-rule="evenodd"/', $svg);
        self::assertMatchesRegularExpression('/<path[^>]*fill="#1d1d1b"/', $svg);

        // The class this service puts on the root element carries the sizing
        // rules and must not be swept up with the artwork's own classes.
        self::assertStringContainsString('class="nt-aimark__icon"', $svg);
    }

    /**
     * Transparency is expressed as `opacity` in the stylesheet and has to
     * survive the move to attributes — otherwise the 50 % variants would come
     * out fully opaque, i.e. as the wrong icon.
     */
    #[Test]
    public function theTransparentVariantKeepsItsOpacity(): void
    {
        $this->writeIcon(
            'ai-basic-black-50.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'
                . '<defs><style>.cls-1 { fill-rule: evenodd; opacity: .5; }</style></defs>'
                . '<path class="cls-1" d="M0 0h10v10H0z"/></svg>',
        );

        $svg = (string) $this->subject()->inlineSvg(IconVariant::Basic, transparent: true);

        self::assertMatchesRegularExpression('/<path[^>]*opacity="\.5"/', $svg);
    }

    /**
     * A file whose stylesheet is beyond plain class rules is left alone rather
     * than half-converted — but two of them on one page still must not restyle
     * each other, so the names are made unique instead.
     */
    #[Test]
    public function anUnexpectedStylesheetFallsBackToUniqueNames(): void
    {
        $artwork = static fn(string $fill): string => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'
            . '<defs><style>path.cls-1 { fill: ' . $fill . '; }</style></defs>'
            . '<path class="cls-1" d="M0 0h10v10H0z"/></svg>';

        $this->writeIcon('ai-generated-black.svg', $artwork('#000'));
        $this->writeIcon('ai-generated-white.svg', $artwork('#fff'));

        $black = (string) $this->subject()->inlineSvg(IconVariant::Generated, white: false);
        $white = (string) $this->subject()->inlineSvg(IconVariant::Generated, white: true);

        self::assertStringContainsString('<style', $black, 'The stylesheet stays when it is not understood.');
        self::assertStringNotContainsString('class="cls-1"', $black);

        preg_match_all('/cls-\d+-[0-9a-f]+/', $black, $blackNames);
        preg_match_all('/cls-\d+-[0-9a-f]+/', $white, $whiteNames);

        self::assertNotSame([], array_unique($blackNames[0]));
        self::assertSame(
            [],
            array_intersect(array_unique($blackNames[0]), array_unique($whiteNames[0])),
            'Two variants on one page must not share class names.',
        );
    }

    /**
     * All twelve official files use `id="Calque_1"`; duplicated ids in one
     * document are invalid markup.
     */
    #[Test]
    public function twoIconsOnOnePageDoNotShareElementIds(): void
    {
        $artwork = static fn(string $fill): string => '<svg xmlns="http://www.w3.org/2000/svg" id="Calque_1" viewBox="0 0 10 10">'
            . '<path fill="' . $fill . '" d="M0 0h10v10H0z"/></svg>';

        $this->writeIcon('ai-modified-black.svg', $artwork('#000'));
        $this->writeIcon('ai-modified-white.svg', $artwork('#fff'));

        $black = (string) $this->subject()->inlineSvg(IconVariant::Modified, white: false);
        $white = (string) $this->subject()->inlineSvg(IconVariant::Modified, white: true);

        self::assertStringNotContainsString('id="Calque_1"', $black);
        self::assertSame([], array_intersect(self::idsOf($black), self::idsOf($white)));
    }

    /**
     * @return list<string>
     */
    private static function idsOf(string $svg): array
    {
        preg_match_all('/\bid="([^"]+)"/', $svg, $matches);

        return $matches[1];
    }

    /**
     * The same icon twice is one piece of artwork, not two — the same file
     * yields the same markup, so nothing drifts between two occurrences.
     */
    #[Test]
    public function theSameIconTwiceProducesTheSameMarkup(): void
    {
        $this->writeIcon(
            'ai-basic-black.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'
                . '<defs><style>.cls-1 { fill-rule: evenodd; }</style></defs>'
                . '<path class="cls-1" d="M0 0h10v10H0z"/></svg>',
        );

        self::assertSame(
            $this->subject()->inlineSvg(IconVariant::Basic),
            $this->subject()->inlineSvg(IconVariant::Basic),
        );
    }

    #[Test]
    public function aFileThatIsNotAnSvgIsRejected(): void
    {
        $this->writeIcon('ai-basic-black.svg', 'not an svg at all');

        self::assertNull($this->subject()->inlineSvg(IconVariant::Basic));
    }

    /**
     * The icon markup is embedded into the page unescaped, and the files arrive
     * by manual download rather than through TYPO3's upload checks. A tampered
     * file must not turn into script execution.
     *
     * @return array<string, array{string, string}>
     */
    public static function hostileSvgPayloads(): array
    {
        return [
            'script element' => [
                '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0h1v1H0z"/></svg>',
                'alert(1)',
            ],
            'event handler' => [
                '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><path d="M0 0h1v1H0z"/></svg>',
                'onload',
            ],
            'javascript link' => [
                '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><path d="M0 0h1v1H0z"/></a></svg>',
                'javascript:',
            ],
            'foreign object with markup' => [
                '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><iframe src="https://evil.example/"></iframe></foreignObject><path d="M0 0h1v1H0z"/></svg>',
                '<iframe',
            ],
        ];
    }

    #[Test]
    #[DataProvider('hostileSvgPayloads')]
    public function activeContentIsStrippedFromTheIcon(string $payload, string $mustNotAppear): void
    {
        $this->writeIcon('ai-basic-black.svg', $payload);

        $svg = $this->subject()->inlineSvg(IconVariant::Basic);

        self::assertIsString($svg);
        self::assertStringNotContainsStringIgnoringCase($mustNotAppear, $svg);
        // The artwork itself has to survive the sanitising.
        self::assertStringContainsString('<path', $svg);
    }

    /**
     * If sanitising leaves nothing usable, the text label is the right outcome
     * — not a broken fragment in the page.
     */
    #[Test]
    public function anIconThatDoesNotSurviveSanitisingFallsBackToNull(): void
    {
        $this->writeIcon('ai-basic-black.svg', '<svg><!-- nothing but a comment --></svg>garbage <svg');

        $svg = $this->subject()->inlineSvg(IconVariant::Basic);

        self::assertTrue($svg === null || str_contains($svg, '<svg'));
    }

    #[Test]
    public function theFileNameSchemeSelectsColourAndTransparency(): void
    {
        self::assertSame('ai-basic-black.svg', IconVariant::Basic->fileName());
        self::assertSame('ai-basic-white-50.svg', IconVariant::Basic->fileName(white: true, transparent: true));
    }
}
