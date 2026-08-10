<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Functional\Service;

use NetThinks\NtAimark\Domain\Enum\TextStatus;
use NetThinks\NtAimark\Domain\Model\TextDeclaration;
use NetThinks\NtAimark\Service\TextDisclosureRuleService;
use NetThinks\NtAimark\Service\TextNoticeRenderService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class TextNoticeRenderServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['netthinks/nt-aimark'];

    private function frontendRequest(): ServerRequest
    {
        $site = new Site('test', 1, [
            'base' => 'https://example.com/',
            'languages' => [
                ['languageId' => 0, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF-8', 'base' => '/'],
            ],
        ]);

        return (new ServerRequest('https://example.com/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage());
    }

    private function render(TextStatus $status, bool $publicInterest, bool $control, string $responsible = ''): string
    {
        $declaration = new TextDeclaration(
            tableName: 'tt_content',
            recordUid: 7,
            status: $status,
            publicInterest: $publicInterest,
            editorialControl: $control,
            responsible: $responsible,
        );
        $decision = (new TextDisclosureRuleService())->resolve($declaration);

        return $this->get(TextNoticeRenderService::class)->render($decision, $this->frontendRequest());
    }

    #[Test]
    public function aGeneratedTextOfPublicInterestGetsASentence(): void
    {
        $html = $this->render(TextStatus::AiGenerated, publicInterest: true, control: false);

        self::assertStringContainsString('nt-aimark-notice', $html);
        self::assertStringContainsString('mit künstlicher Intelligenz erzeugt', $html);
    }

    #[Test]
    public function aRevisedDraftGetsItsOwnWording(): void
    {
        $html = $this->render(TextStatus::AiDraftRevised, publicInterest: true, control: false);

        self::assertStringContainsString('redaktionell überarbeitet', $html);
    }

    /**
     * The notice is an aside with an accessible name, not a bare paragraph
     * floating in the content.
     */
    #[Test]
    public function theNoticeIsAnAsideWithAnAccessibleName(): void
    {
        $html = $this->render(TextStatus::AiGenerated, publicInterest: true, control: false);

        self::assertStringContainsString('<aside', $html);
        self::assertMatchesRegularExpression('/aria-label="[^"]+"/', $html);
    }

    #[Test]
    public function aTextWithoutAiRendersNothing(): void
    {
        self::assertSame('', $this->render(TextStatus::NoAi, publicInterest: true, control: false));
    }

    #[Test]
    public function aTextThatIsNotOfPublicInterestRendersNothing(): void
    {
        self::assertSame('', $this->render(TextStatus::AiGenerated, publicInterest: false, control: false));
    }

    #[Test]
    public function aReviewedTextWithANamedPersonRendersNothing(): void
    {
        self::assertSame(
            '',
            $this->render(TextStatus::AiGenerated, true, true, 'Dietmar Engler'),
        );
    }

    /**
     * A tick box with nobody behind it does not complete the exception, so the
     * notice stays.
     */
    #[Test]
    public function aReviewWithoutANamedPersonStillRendersTheNotice(): void
    {
        $html = $this->render(TextStatus::AiGenerated, publicInterest: true, control: true);

        self::assertStringContainsString('nt-aimark-notice', $html);
    }

    /**
     * The responsible person is internal documentation, not something to put
     * in front of visitors.
     */
    #[Test]
    public function theResponsiblePersonNeverReachesTheMarkup(): void
    {
        $html = $this->render(TextStatus::AiGenerated, true, false, 'Dietmar Engler');

        self::assertStringNotContainsString('Dietmar Engler', $html);
    }
}
