<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Service;

use NetThinks\NtAimark\Service\AiMarkSettingsFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AiMarkSettingsFactoryTest extends UnitTestCase
{
    private AiMarkSettingsFactory $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new AiMarkSettingsFactory();
    }

    /**
     * @param array<string, mixed> $ntAimark
     */
    private function siteWith(array $ntAimark): Site
    {
        return new Site('test', 1, [
            'base' => 'https://example.com/',
            'settings' => ['ntAimark' => $ntAimark],
        ]);
    }

    #[Test]
    public function withoutASiteTheDefaultsApply(): void
    {
        $settings = $this->subject->fromRequest(new ServerRequest('https://example.com/'));

        self::assertFalse($settings->labelUnknownOrigin);
        self::assertTrue($settings->useFileRenderer);
        self::assertSame('bottom-right', $settings->badgePosition);
        self::assertSame('medium', $settings->badgeSize);

        // The quiet default: the official icon and nothing else. Neither the
        // detail panel nor the wording beside it is required by Art. 50(4),
        // and both add to the label rather than to the disclosure.
        self::assertFalse($settings->showDetails);
        self::assertFalse($settings->showTextLabel);
    }

    #[Test]
    public function withoutARequestAtAllTheDefaultsApply(): void
    {
        self::assertSame('medium', $this->subject->fromRequest(null)->badgeSize);
    }

    #[Test]
    public function siteSettingsAreCarriedThrough(): void
    {
        $settings = $this->subject->fromSite($this->siteWith([
            'labelUnknownOrigin' => true,
            'useFileRenderer' => false,
            'showDetails' => false,
            'badgePosition' => 'top-left',
            'badgeSize' => 'large',
        ]));

        self::assertTrue($settings->labelUnknownOrigin);
        self::assertFalse($settings->useFileRenderer);
        self::assertFalse($settings->showDetails);
        self::assertSame('top-left', $settings->badgePosition);
        self::assertSame('large', $settings->badgeSize);
    }

    /**
     * Position and size end up in a CSS class name. Whatever a site
     * configuration contains, only known values may get that far.
     *
     * @return array<string, array{string, string}>
     */
    public static function unexpectedPositions(): array
    {
        return [
            'typo' => ['bottom_right', 'bottom-right'],
            'empty' => ['', 'bottom-right'],
            'markup' => ['" onmouseover="alert(1)', 'bottom-right'],
            'valid value passes' => ['top-right', 'top-right'],
        ];
    }

    #[Test]
    #[DataProvider('unexpectedPositions')]
    public function onlyKnownPositionsSurvive(string $configured, string $expected): void
    {
        self::assertSame(
            $expected,
            $this->subject->fromSite($this->siteWith(['badgePosition' => $configured]))->badgePosition,
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unexpectedSizes(): array
    {
        return [
            'unknown' => ['gigantic', 'medium'],
            'empty' => ['', 'medium'],
            'markup' => ['<script>', 'medium'],
            'valid value passes' => ['small', 'small'],
        ];
    }

    #[Test]
    #[DataProvider('unexpectedSizes')]
    public function onlyKnownSizesSurvive(string $configured, string $expected): void
    {
        self::assertSame(
            $expected,
            $this->subject->fromSite($this->siteWith(['badgeSize' => $configured]))->badgeSize,
        );
    }
}
