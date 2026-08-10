<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Domain;

use NetThinks\NtAimark\Domain\AiActCutoff;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AiActCutoffTest extends UnitTestCase
{
    #[Test]
    public function timestampMatchesTheDateArticleFiftyBecameApplicable(): void
    {
        self::assertSame(
            '2026-08-02T00:00:00+00:00',
            (new \DateTimeImmutable('@' . AiActCutoff::TIMESTAMP))->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function creationDates(): array
    {
        return [
            'unset date is not an exemption' => [0, false],
            'day before the cutoff' => [AiActCutoff::TIMESTAMP - 86400, true],
            'one second before the cutoff' => [AiActCutoff::TIMESTAMP - 1, true],
            'exactly the cutoff' => [AiActCutoff::TIMESTAMP, false],
            'after the cutoff' => [AiActCutoff::TIMESTAMP + 86400, false],
        ];
    }

    #[Test]
    #[DataProvider('creationDates')]
    public function isBeforeRecognisesContentPredatingTheObligation(int $createdAt, bool $expected): void
    {
        self::assertSame($expected, AiActCutoff::isBefore($createdAt));
    }
}
