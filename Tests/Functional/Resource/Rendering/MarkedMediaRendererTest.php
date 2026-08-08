<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Functional\Resource\Rendering;

use NetThinks\NtAimark\Resource\Rendering\MarkedMediaRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Rendering\AudioTagRenderer;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Resource\Rendering\VideoTagRenderer;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class MarkedMediaRendererTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['netthinks/nt-aimark'];

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function givenFrontendRequest(array $settings = []): void
    {
        $site = new Site('test', 1, [
            'base' => 'https://example.com/',
            'languages' => [
                ['languageId' => 0, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF-8', 'base' => '/'],
            ],
            'settings' => ['ntAimark' => $settings],
        ]);

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.com/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage());
    }

    private function fileWithMimeType(string $mimeType): FileInterface
    {
        $file = $this->createStub(FileInterface::class);
        $file->method('getMimeType')->willReturn($mimeType);

        return $file;
    }

    #[Test]
    public function theRendererIsRegisteredAboveTheCoreRenderers(): void
    {
        $registered = array_filter(
            GeneralUtility::makeInstance(RendererRegistry::class)->getRendererInstances(),
            static fn(object $renderer): bool => $renderer instanceof MarkedMediaRenderer,
        );

        self::assertCount(1, $registered);
        self::assertGreaterThan(
            (new AudioTagRenderer())->getPriority(),
            (new MarkedMediaRenderer())->getPriority(),
        );
        self::assertGreaterThan(
            (new VideoTagRenderer())->getPriority(),
            (new MarkedMediaRenderer())->getPriority(),
        );
    }

    /**
     * The central scoping decision: TYPO3 ships no image file renderer, so
     * there would be nothing to delegate to. Claiming images here would mean
     * forking MediaViewHelper's private rendering.
     *
     * @return array<string, array{string}>
     */
    public static function imageMimeTypes(): array
    {
        return [
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'svg' => ['image/svg+xml'],
        ];
    }

    #[Test]
    #[DataProvider('imageMimeTypes')]
    public function imagesAreNeverClaimed(string $mimeType): void
    {
        $this->givenFrontendRequest();

        self::assertFalse((new MarkedMediaRenderer())->canRender($this->fileWithMimeType($mimeType)));
    }

    #[Test]
    public function nothingIsClaimedWhenTheSiteSwitchesTheRendererOff(): void
    {
        $this->givenFrontendRequest(['useFileRenderer' => false]);

        self::assertFalse((new MarkedMediaRenderer())->canRender($this->fileWithMimeType('audio/mpeg')));
    }

    /**
     * Outside a frontend request there is no site and therefore no decision to
     * make. Claiming the file anyway would strip the core output.
     */
    #[Test]
    public function nothingIsClaimedWithoutARequest(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);

        self::assertFalse((new MarkedMediaRenderer())->canRender($this->fileWithMimeType('audio/mpeg')));
    }

    /**
     * The registry hands back the highest-priority renderer, which is this one.
     * Looking up the delegate has to skip itself, or the lookup recurses until
     * the stack runs out.
     */
    #[Test]
    public function resolvingTheDelegateDoesNotRecurseIntoItself(): void
    {
        $this->givenFrontendRequest();

        // canRender walks the registry, which contains this renderer. Reaching
        // this assertion at all is the point of the test.
        self::assertFalse((new MarkedMediaRenderer())->canRender($this->fileWithMimeType('audio/mpeg')));
    }

    /**
     * An unlabelled file has to come out of the core renderer untouched.
     */
    #[Test]
    public function anUnlabelledMediaFileIsLeftToTheCoreRenderer(): void
    {
        $this->givenFrontendRequest();

        $file = $this->fileWithMimeType('audio/mpeg');
        $renderer = GeneralUtility::makeInstance(RendererRegistry::class)->getRenderer($file);

        self::assertNotInstanceOf(MarkedMediaRenderer::class, $renderer);
        self::assertInstanceOf(AudioTagRenderer::class, $renderer);
    }
}
