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
        self::assertStringContainsString('viewBox="0 0 10 10"', $svg);
    }

    /**
     * The official artwork is the reason this exists.
     *
     * All twelve files the Commission publishes declare the same `.cls-1` /
     * `.cls-2` inside their own `<style>` block and the same `id="Calque_1"`.
     * Inlined together — which happens on any page mixing a light and a dark
     * image — the later block would restyle the earlier icon and the ids would
     * be duplicated. Each copy therefore gets its own suffix.
     */
    #[Test]
    public function twoIconsOnOnePageDoNotRestyleEachOther(): void
    {
        $artwork = static fn(string $discFill): string => '<svg xmlns="http://www.w3.org/2000/svg" id="Calque_1" viewBox="0 0 10 10">'
            . '<defs><style>.cls-1 { ' . $discFill . ' } .cls-2 { fill: #fff; }</style></defs>'
            . '<path class="cls-1" d="M0 0h10v10H0z"/><path class="cls-2" d="M2 2h6v6H2z"/></svg>';

        $this->writeIcon('ai-generated-black.svg', $artwork('fill-rule: evenodd;'));
        $this->writeIcon('ai-generated-white.svg', $artwork('fill: #fff;'));

        $black = (string) $this->subject()->inlineSvg(IconVariant::Generated, white: false);
        $white = (string) $this->subject()->inlineSvg(IconVariant::Generated, white: true);

        self::assertStringNotContainsString('.cls-1 {', $black, 'The generic class name must not survive.');
        self::assertStringNotContainsString('id="Calque_1"', $black);

        // Whatever names the two ended up with, they must not be the same ones.
        preg_match_all('/cls-\d+-[0-9a-f]+/', $black, $blackNames);
        preg_match_all('/cls-\d+-[0-9a-f]+/', $white, $whiteNames);

        self::assertNotSame([], array_unique($blackNames[0]));
        self::assertSame(
            [],
            array_intersect(array_unique($blackNames[0]), array_unique($whiteNames[0])),
            'Two variants on one page must not share class names.',
        );
        self::assertSame(
            [],
            array_intersect(
                self::idsOf($black),
                self::idsOf($white),
            ),
            'Two variants on one page must not share element ids.',
        );

        // The rule still has to reach its own paths.
        foreach ([$black, $white] as $svg) {
            preg_match('/\.(cls-1-[0-9a-f]+)/', $svg, $rule);
            self::assertNotSame([], $rule);
            self::assertStringContainsString('class="' . $rule[1] . '"', $svg);
        }
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
     * The same icon twice is one piece of artwork, not two — it stays a single
     * shared rule set rather than duplicating the styles per occurrence.
     */
    #[Test]
    public function theSameIconTwiceKeepsOneSetOfNames(): void
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
