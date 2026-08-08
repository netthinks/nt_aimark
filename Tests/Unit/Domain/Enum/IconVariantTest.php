<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Domain\Enum;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\IconVariant;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class IconVariantTest extends UnitTestCase
{
    /**
     * @return array<string, array{AiStatus, IconVariant}>
     */
    public static function statusToIcon(): array
    {
        return [
            'generated' => [AiStatus::Generated, IconVariant::Generated],
            'modified' => [AiStatus::Modified, IconVariant::Modified],
            'unknown origin falls back to the unspecific icon' => [AiStatus::UnknownOrigin, IconVariant::Basic],
            'no AI has no icon' => [AiStatus::NoAi, IconVariant::None],
            'unreviewed has no icon' => [AiStatus::Unreviewed, IconVariant::None],
            'suggestion has no icon' => [AiStatus::Suggested, IconVariant::None],
        ];
    }

    #[Test]
    #[DataProvider('statusToIcon')]
    public function defaultForStatusMapsConfirmedStatesOnly(AiStatus $status, IconVariant $expected): void
    {
        self::assertSame($expected, IconVariant::defaultForStatus($status));
    }

    /**
     * @return array<string, array{IconVariant, bool, bool, string}>
     */
    public static function fileNames(): array
    {
        return [
            'basic black' => [IconVariant::Basic, false, false, 'ai-basic-black.svg'],
            'basic white' => [IconVariant::Basic, true, false, 'ai-basic-white.svg'],
            'generated black transparent' => [IconVariant::Generated, false, true, 'ai-generated-black-50.svg'],
            'modified white transparent' => [IconVariant::Modified, true, true, 'ai-modified-white-50.svg'],
            'none has no file' => [IconVariant::None, false, false, ''],
        ];
    }

    #[Test]
    #[DataProvider('fileNames')]
    public function fileNameFollowsTheEuIconNamingScheme(
        IconVariant $variant,
        bool $white,
        bool $transparent,
        string $expected,
    ): void {
        self::assertSame($expected, $variant->fileName($white, $transparent));
    }
}
