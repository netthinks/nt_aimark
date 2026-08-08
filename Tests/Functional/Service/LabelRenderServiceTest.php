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
        $html = $this->render($this->generatedDeclaration());

        self::assertStringContainsString('nt-aimark__badge-fallback', $html);
        self::assertStringNotContainsString('<svg', $html);
        // The fixture site is German, so the label comes from de.locallang.xlf.
        self::assertStringContainsString('KI-generiert', $html);
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

        preg_match('/id="([^"]+)"/', $first, $firstId);
        preg_match('/id="([^"]+)"/', $second, $secondId);
        self::assertArrayHasKey(1, $firstId);
        self::assertArrayHasKey(1, $secondId);

        self::assertNotSame($firstId[1], $secondId[1]);
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
