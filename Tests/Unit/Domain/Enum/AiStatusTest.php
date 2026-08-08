<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Domain\Enum;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AiStatusTest extends UnitTestCase
{
    /**
     * Persisted values: a change here silently reinterprets existing records.
     *
     * @return array<string, array{AiStatus, int}>
     */
    public static function persistedValues(): array
    {
        return [
            'Unreviewed' => [AiStatus::Unreviewed, 0],
            'NoAi' => [AiStatus::NoAi, 1],
            'Generated' => [AiStatus::Generated, 2],
            'Modified' => [AiStatus::Modified, 3],
            'UnknownOrigin' => [AiStatus::UnknownOrigin, 4],
            'Suggested' => [AiStatus::Suggested, 5],
        ];
    }

    #[Test]
    #[DataProvider('persistedValues')]
    public function databaseValuesAreStable(AiStatus $status, int $expected): void
    {
        self::assertSame($expected, $status->value);
    }

    /**
     * @return array<string, array{AiStatus, bool}>
     */
    public static function reviewExpectations(): array
    {
        return [
            'unreviewed needs review' => [AiStatus::Unreviewed, true],
            'unconfirmed suggestion needs review' => [AiStatus::Suggested, true],
            'no AI is settled' => [AiStatus::NoAi, false],
            'generated is settled' => [AiStatus::Generated, false],
            'modified is settled' => [AiStatus::Modified, false],
            'unknown origin is settled' => [AiStatus::UnknownOrigin, false],
        ];
    }

    #[Test]
    #[DataProvider('reviewExpectations')]
    public function requiresReviewCoversUnreviewedAndSuggested(AiStatus $status, bool $expected): void
    {
        self::assertSame($expected, $status->requiresReview());
    }

    /**
     * @return array<string, array{AiStatus, bool}>
     */
    public static function confirmedAiUseExpectations(): array
    {
        return [
            'generated' => [AiStatus::Generated, true],
            'modified' => [AiStatus::Modified, true],
            'a suggestion is not a confirmation' => [AiStatus::Suggested, false],
            'unreviewed' => [AiStatus::Unreviewed, false],
            'no AI' => [AiStatus::NoAi, false],
            'unknown origin' => [AiStatus::UnknownOrigin, false],
        ];
    }

    #[Test]
    #[DataProvider('confirmedAiUseExpectations')]
    public function onlyHumanConfirmedStatesCountAsAiUse(AiStatus $status, bool $expected): void
    {
        self::assertSame($expected, $status->isConfirmedAiUse());
    }
}
