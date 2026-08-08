<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Functional\Service;

use NetThinks\NtAimark\Service\AuditService;
use NetThinks\NtAimark\Service\MetaDataAuditRecorder;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class MetaDataAuditRecorderTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['netthinks/nt-aimark'];

    private MetaDataAuditRecorder $subject;

    private AuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(MetaDataAuditRecorder::class);
        $this->auditService = $this->get(AuditService::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entries(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(AuditService::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        /** @var list<array<string, mixed>> $rows */
        $rows = $queryBuilder
            ->select('*')
            ->from(AuditService::TABLE)
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();

        return $rows;
    }

    #[Test]
    public function aChangedFieldIsRecordedWithItsPreviousValue(): void
    {
        $this->subject->record(42, ['tx_ntaimark_status' => 2]);

        $entries = $this->entries();

        self::assertCount(1, $entries);
        self::assertSame('tx_ntaimark_status', $entries[0]['field_name']);
        // No history yet, so the field default stands in as the old value.
        self::assertSame('0', $entries[0]['old_value']);
        self::assertSame('2', $entries[0]['new_value']);
        self::assertSame(AuditService::SOURCE_MANUAL, $entries[0]['source']);
    }

    #[Test]
    public function afieldThatDidNotChangeIsNotRecorded(): void
    {
        // 0 is the default, so an unrelated save carrying the untouched field
        // must not look like a change.
        $this->subject->record(42, ['tx_ntaimark_status' => 0]);

        self::assertSame([], $this->entries());
    }

    #[Test]
    public function fieldsOutsideTheWatchedSetAreIgnored(): void
    {
        $this->subject->record(42, ['title' => 'A new title', 'alternative' => 'Alt text']);

        self::assertSame([], $this->entries());
    }

    /**
     * The mechanism that keeps the two entry points from logging the same
     * change twice: an explicit entry written by the extension is already in
     * the trail, so the generic path sees no difference.
     */
    #[Test]
    public function aChangeAlreadyInTheTrailIsNotRecordedAgain(): void
    {
        $this->auditService->log(
            MetaDataAuditRecorder::TABLE,
            42,
            'bulk_review',
            AuditService::SOURCE_MANUAL,
            'tx_ntaimark_status',
            0,
            2,
        );

        $this->subject->record(42, ['tx_ntaimark_status' => 2]);

        $entries = $this->entries();

        self::assertCount(1, $entries);
        // And the richer action survives rather than being replaced by a
        // plain "update".
        self::assertSame('bulk_review', $entries[0]['action']);
    }

    #[Test]
    public function aFurtherChangeIsMeasuredAgainstTheLastRecordedValue(): void
    {
        $this->subject->record(42, ['tx_ntaimark_status' => 2]);
        $this->subject->record(42, ['tx_ntaimark_status' => 3]);

        $entries = $this->entries();

        self::assertCount(2, $entries);
        self::assertSame('2', $entries[1]['old_value']);
        self::assertSame('3', $entries[1]['new_value']);
    }

    #[Test]
    public function severalChangedFieldsGetOneEntryEach(): void
    {
        $this->subject->record(42, [
            'tx_ntaimark_status' => 2,
            'tx_ntaimark_system' => 'Midjourney',
            'tx_ntaimark_vendor' => '',
        ]);

        $fields = array_map(static fn(array $row): string => (string) $row['field_name'], $this->entries());

        self::assertSame(['tx_ntaimark_status', 'tx_ntaimark_system'], $fields);
    }

    #[Test]
    public function recordsWithoutAUidAreIgnored(): void
    {
        $this->subject->record(0, ['tx_ntaimark_status' => 2]);

        self::assertSame([], $this->entries());
    }

    /**
     * The trail must stay readable after the backend user is gone, so the name
     * is stored alongside the id.
     */
    #[Test]
    public function theUserNameIsStoredAlongsideTheId(): void
    {
        $this->subject->record(42, ['tx_ntaimark_status' => 2]);

        $entries = $this->entries();

        self::assertArrayHasKey('be_user_name', $entries[0]);
        self::assertArrayHasKey('be_user', $entries[0]);
    }
}
