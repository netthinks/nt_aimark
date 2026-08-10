<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Functional\Service;

use NetThinks\NtAimark\Domain\AiActCutoff;
use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Model\AiDeclaration;
use NetThinks\NtAimark\Domain\Model\AiMarkSettings;
use NetThinks\NtAimark\Service\DisclosureRuleService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Keeps the accessibility fixture honest.
 *
 * The axe-core run in Tests/Acceptance works against a static page, so it needs
 * no database and no TYPO3 boot. That only proves something as long as the
 * page still contains what the renderer actually emits — which is what this
 * test checks. Change the Fluid template and this fails until the fixture
 * follows.
 */
final class MarkupFixtureTest extends FunctionalTestCase
{
    use IconDirectoryTrait;

    protected array $testExtensionsToLoad = ['netthinks/nt-aimark'];

    private const FIXTURE = __DIR__ . '/../../Acceptance/fixtures/labelled-page.html';

    #[Test]
    public function theAccessibilityFixtureStillMatchesTheRenderer(): void
    {
        $site = new Site('test', 1, [
            'base' => 'https://example.com/',
            'languages' => [
                ['languageId' => 0, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF-8', 'base' => '/'],
            ],
        ]);
        $request = (new ServerRequest('https://example.com/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage());

        $declaration = new AiDeclaration(
            tableName: 'sys_file_metadata',
            recordUid: 42,
            status: AiStatus::Generated,
            system: 'DALL·E 3',
            vendor: 'OpenAI',
            createdAt: AiActCutoff::TIMESTAMP + 86400,
        );
        $decision = (new DisclosureRuleService())->resolve($declaration, new AiMarkSettings());

        // The fixture documents the icon-less case, so it is rendered that way
        // on purpose — otherwise it would match only on machines where the EU
        // icons happen to have been downloaded.
        $rendered = $this->rendererWithoutIcons()->renderBadge($declaration, $decision, $request);
        $fixture = (string) file_get_contents(self::FIXTURE);
        $this->removeEmptyIconDirectory();

        self::assertStringContainsString(
            $this->normalise($rendered),
            $this->normalise($fixture),
            'Tests/Acceptance/fixtures/labelled-page.html no longer contains the markup the renderer produces. '
                . 'Update the fixture so the axe-core run keeps testing the real output.',
        );
    }

    private function normalise(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $html));
    }
}
