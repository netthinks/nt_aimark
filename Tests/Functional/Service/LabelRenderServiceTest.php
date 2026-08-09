<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Functional\Service;

use NetThinks\NtAimark\Domain\AiActCutoff;
use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\DisclosureMode;
use NetThinks\NtAimark\Domain\Enum\IconVariant;
use NetThinks\NtAimark\Domain\Model\AiDeclaration;
use NetThinks\NtAimark\Domain\Model\AiMarkSettings;
use NetThinks\NtAimark\Service\DisclosureRuleService;
use NetThinks\NtAimark\Service\LabelRenderService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Renders the real markup through the real Fluid partial.
 */
final class LabelRenderServiceTest extends FunctionalTestCase
{
    use IconDirectoryTrait;

    protected array $testExtensionsToLoad = ['netthinks/nt-aimark'];

    private function render(AiDeclaration $declaration, bool $showDetails = true): string
    {
        $decision = (new DisclosureRuleService())->resolve($declaration, new AiMarkSettings());

        return $this->get(LabelRenderService::class)->renderBadge(
            $declaration,
            $decision,
            $this->frontendRequest(),
            'bottom-right',
            'medium',
            $showDetails,
        );
    }


    /**
     * f:translate resolves the locale through the application type and the
     * site language, so the request has to look like a real frontend request.
     */
    private function frontendRequest(): ServerRequest
    {
        $site = new Site('test', 1, [
            'base' => 'https://example.com/',
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'Deutsch',
                    'locale' => 'de_DE.UTF-8',
                    'base' => '/',
                ],
            ],
        ]);

        return (new ServerRequest('https://example.com/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage());
    }

    private function generatedDeclaration(): AiDeclaration
    {
        return new AiDeclaration(
            tableName: 'sys_file_metadata',
            recordUid: 42,
            status: AiStatus::Generated,
            system: 'DALL·E 3',
            vendor: 'OpenAI',
            createdAt: AiActCutoff::TIMESTAMP + 86400,
        );
    }

    #[Test]
    public function aLabelledFileRendersAnAccessibleBadge(): void
    {
        $html = $this->render($this->generatedDeclaration());

        self::assertStringContainsString('nt-aimark__badge', $html);
        self::assertStringContainsString('nt-aimark__badge--bottom-right', $html);
        self::assertStringContainsString('data-ai-variant="generated"', $html);
    }

    /**
     * Without the downloaded EU icons the badge has to stay perceivable.
     */
    #[Test]
    public function withoutTheIconFilesTheBadgeFallsBackToText(): void
    {
        $declaration = $this->generatedDeclaration();
        $decision = (new DisclosureRuleService())->resolve($declaration, new AiMarkSettings());

        $html = $this->rendererWithoutIcons()->renderBadge($declaration, $decision, $this->frontendRequest());

        self::assertStringContainsString('nt-aimark__badge-fallback', $html);
        self::assertStringNotContainsString('<svg', $html);
        // The fixture site is German, so the label comes from de.locallang.xlf.
        self::assertStringContainsString('KI-generiert', $html);

        $this->removeEmptyIconDirectory();
    }

    #[Test]
    public function theDetailPanelIsKeyboardOperableAndStartsCollapsed(): void
    {
        $html = $this->render($this->generatedDeclaration());

        self::assertStringContainsString('<button type="button"', $html);
        self::assertStringContainsString('aria-expanded="false"', $html);
        self::assertMatchesRegularExpression('/aria-controls="(aimark-detail-42-\d+)"/', $html);

        preg_match('/aria-controls="([^"]+)"/', $html, $matches);
        self::assertArrayHasKey(1, $matches);
        self::assertStringContainsString('id="' . $matches[1] . '"', $html);
        self::assertStringContainsString('hidden', $html);
    }

    /**
     * The same image can appear twice on a page; duplicate ids would break the
     * aria-controls relationship for the second one.
     */
    #[Test]
    public function repeatedRenderingProducesUniqueDetailIds(): void
    {
        $service = $this->get(LabelRenderService::class);
        $declaration = $this->generatedDeclaration();
        $decision = (new DisclosureRuleService())->resolve($declaration, new AiMarkSettings());
        $request = $this->frontendRequest();

        $first = $service->renderBadge($declaration, $decision, $request);
        $second = $service->renderBadge($declaration, $decision, $request);

        // Specifically the detail panel: an inlined icon brings ids of its own,
        // and matching the first id in the markup found those instead.
        preg_match('/id="(aimark-detail-[^"]+)"/', $first, $firstId);
        preg_match('/id="(aimark-detail-[^"]+)"/', $second, $secondId);
        self::assertArrayHasKey(1, $firstId);
        self::assertArrayHasKey(1, $secondId);

        self::assertNotSame($firstId[1], $secondId[1]);
    }

    /**
     * The badge is positioned against its frame, and the frame holds nothing
     * but the picture.
     *
     * Without it the badge positions against the whole figure — which also
     * contains the toggle and the detail panel — and "bottom right" lands
     * below the image, on the page background. The black-or-white choice made
     * from the image is then measured against something else entirely, which
     * is how the contrast guarantee quietly stops holding. Seen on this
     * project's own site before it was fixed.
     */
    #[Test]
    public function theBadgeIsFramedWithTheImageAndNotWithTheToggle(): void
    {
        $declaration = $this->generatedDeclaration();
        $decision = (new DisclosureRuleService())->resolve($declaration, new AiMarkSettings());

        $html = $this->get(LabelRenderService::class)->renderBadge(
            $declaration,
            $decision,
            $this->frontendRequest(),
            'bottom-right',
            'medium',
            true,
            null,
            '<img src="/example.jpg" alt="">',
        );

        self::assertMatchesRegularExpression(
            '#<span class="nt-aimark__frame"><img[^>]*><span class="nt-aimark__badge[^"]*"#',
            $html,
            'The frame has to wrap image and badge together.',
        );

        // Toggle and detail panel stay outside — they must not stretch the box
        // the badge is positioned against.
        $frame = (string) preg_replace('#^.*<span class="nt-aimark__frame">(.*?)</span></span>.*$#s', '$1', $html);

        self::assertStringNotContainsString('nt-aimark__toggle', $frame);
        self::assertStringNotContainsString('nt-aimark__detail', $frame);
        self::assertStringContainsString('nt-aimark__toggle', $html);
    }

    /**
     * The official icons carry an English wordmark and must not be redrawn or
     * translated — they are the Commission's artwork and apply unchanged
     * across the Union. The meaning therefore travels in the wording beside
     * them, in the language of the site.
     */
    #[Test]
    public function theIconIsAccompaniedByWordingInTheSiteLanguage(): void
    {
        $declaration = $this->generatedDeclaration();
        $decision = (new DisclosureRuleService())->resolve($declaration, new AiMarkSettings());

        $html = $this->rendererWithIcons()->renderBadge(
            $declaration,
            $decision,
            $this->frontendRequest(),
            showTextLabel: true,
        );

        self::assertStringContainsString('<svg', $html, 'The official icon still carries the badge.');
        self::assertStringContainsString('KI-generiert', $html);

        $this->removeEmptyIconDirectory();
    }

    #[Test]
    public function theWordingCanBeSwitchedOff(): void
    {
        $declaration = $this->generatedDeclaration();
        $decision = (new DisclosureRuleService())->resolve($declaration, new AiMarkSettings());

        $html = $this->rendererWithIcons()->renderBadge(
            $declaration,
            $decision,
            $this->frontendRequest(),
            showTextLabel: false,
        );

        self::assertStringContainsString('<svg', $html);
        self::assertStringNotContainsString('nt-aimark__badge-text', $html);

        $this->removeEmptyIconDirectory();
    }

    /**
     * Without the icon the badge already falls back to the same words. Adding
     * the standard wording on top would say it twice.
     */
    #[Test]
    public function withoutAnIconTheWordingIsNotRepeated(): void
    {
        $declaration = $this->generatedDeclaration();
        $decision = (new DisclosureRuleService())->resolve($declaration, new AiMarkSettings());

        $html = $this->rendererWithoutIcons()->renderBadge(
            $declaration,
            $decision,
            $this->frontendRequest(),
            showTextLabel: true,
        );

        self::assertSame(1, substr_count($html, 'KI-generiert'));

        $this->removeEmptyIconDirectory();
    }

    /**
     * Used on its own, without image markup, there is nothing to frame.
     */
    #[Test]
    public function withoutImageMarkupNoFrameIsEmitted(): void
    {
        self::assertStringNotContainsString('nt-aimark__frame', $this->render($this->generatedDeclaration()));
    }

    #[Test]
    public function theDetailPanelShowsTheRecordedSystemAndDate(): void
    {
        $html = $this->render($this->generatedDeclaration());

        self::assertStringContainsString('DALL·E 3 (OpenAI)', $html);
        self::assertStringContainsString('03.08.2026', $html);
    }

    /**
     * The prompt is internal documentation and must never reach a visitor.
     */
    #[Test]
    public function thePromptNeverReachesTheMarkup(): void
    {
        $declaration = new AiDeclaration(
            tableName: 'sys_file_metadata',
            recordUid: 42,
            status: AiStatus::Generated,
            prompt: 'SECRET-PROMPT-TEXT',
            createdAt: AiActCutoff::TIMESTAMP + 86400,
            notes: 'SECRET-INTERNAL-NOTE',
        );

        $html = $this->render($declaration);

        self::assertStringNotContainsString('SECRET-PROMPT-TEXT', $html);
        self::assertStringNotContainsString('SECRET-INTERNAL-NOTE', $html);
    }

    #[Test]
    public function editorialTextIsEscapedRatherThanInterpreted(): void
    {
        $declaration = new AiDeclaration(
            tableName: 'sys_file_metadata',
            recordUid: 42,
            status: AiStatus::Generated,
            labelText: '<img src=x onerror=alert(1)>',
            createdAt: AiActCutoff::TIMESTAMP + 86400,
        );

        $html = $this->render($declaration);

        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('&lt;img src=x', $html);
    }

    #[Test]
    public function aDecisionAgainstLabellingRendersNothing(): void
    {
        $declaration = new AiDeclaration(
            tableName: 'sys_file_metadata',
            recordUid: 42,
            status: AiStatus::Generated,
            disclosure: DisclosureMode::Exempt,
        );

        self::assertSame('', $this->render($declaration));
    }

    /**
     * An unconfirmed suggestion must never become a visible claim.
     */
    #[Test]
    public function anUnconfirmedSuggestionRendersNothing(): void
    {
        $declaration = new AiDeclaration(
            tableName: 'sys_file_metadata',
            recordUid: 42,
            status: AiStatus::Suggested,
            system: 'Midjourney',
            createdAt: AiActCutoff::TIMESTAMP + 86400,
        );

        self::assertSame('', $this->render($declaration));
    }

    #[Test]
    public function assetsAreOnlyCollectedWhenSomethingIsRendered(): void
    {
        $collector = $this->get(AssetCollector::class);
        self::assertArrayNotHasKey('ntAimark', $collector->getStyleSheets());

        $this->render($this->generatedDeclaration());

        self::assertArrayHasKey('ntAimark', $collector->getStyleSheets());
        self::assertArrayHasKey('ntAimarkDetails', $collector->getJavaScripts());
    }

    #[Test]
    public function theDetailPanelCanBeSuppressed(): void
    {
        $html = $this->render($this->generatedDeclaration(), showDetails: false);

        self::assertStringNotContainsString('nt-aimark__toggle', $html);
        self::assertStringContainsString('nt-aimark__badge', $html);
    }

    /**
     * Position and size end up in a class name; anything unexpected must not
     * leak into the markup.
     */
    #[Test]
    public function anInvalidPositionFallsBackInsteadOfLeaking(): void
    {
        $declaration = $this->generatedDeclaration();
        $decision = (new DisclosureRuleService())->resolve($declaration, new AiMarkSettings());

        $html = $this->get(LabelRenderService::class)->renderBadge(
            $declaration,
            $decision,
            $this->frontendRequest(),
            '" onmouseover="alert(1)',
            'medium',
        );

        self::assertStringNotContainsString('onmouseover', $html);
        self::assertStringContainsString('nt-aimark__badge--bottom-right', $html);
    }

    #[Test]
    public function anExplicitTextOnlyChoiceIsRespected(): void
    {
        $declaration = new AiDeclaration(
            tableName: 'sys_file_metadata',
            recordUid: 42,
            status: AiStatus::Generated,
            icon: IconVariant::None,
            labelText: 'Stimmen erzeugt mit KI',
            createdAt: AiActCutoff::TIMESTAMP + 86400,
        );

        $html = $this->render($declaration);

        self::assertStringContainsString('Stimmen erzeugt mit KI', $html);
        self::assertStringContainsString('data-ai-variant="none"', $html);
    }
}
