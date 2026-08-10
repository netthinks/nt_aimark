<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Functional\Domain\Repository;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\C2paState;
use NetThinks\NtAimark\Domain\Repository\TransparencyRepository;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class TransparencyRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['netthinks/nt-aimark'];

    private TransparencyRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(TransparencyRepository::class);
        $this->givenFiles();
    }

    /**
     * One file per AI status, plus one with a broken signature.
     */
    private function givenFiles(): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $storage = $connectionPool->getConnectionForTable('sys_file_storage');
        $storage->insert('sys_file_storage', ['uid' => 1, 'name' => 'Test storage', 'pid' => 0]);

        $files = $connectionPool->getConnectionForTable('sys_file');
        $metadata = $connectionPool->getConnectionForTable('sys_file_metadata');

        $fixtures = [
            [1, AiStatus::Unreviewed, C2paState::None],
            [2, AiStatus::Suggested, C2paState::None],
            [3, AiStatus::NoAi, C2paState::None],
            [4, AiStatus::Generated, C2paState::Valid],
            [5, AiStatus::Modified, C2paState::Broken],
            [6, AiStatus::UnknownOrigin, C2paState::None],
        ];

        foreach ($fixtures as [$uid, $status, $c2pa]) {
            $files->insert('sys_file', [
                'uid' => $uid,
                'pid' => 0,
                'storage' => 1,
                'identifier' => '/file-' . $uid . '.jpg',
                'name' => 'file-' . $uid . '.jpg',
                'mime_type' => 'image/jpeg',
                'missing' => 0,
            ]);
            $metadata->insert('sys_file_metadata', [
                'pid' => 0,
                'file' => $uid,
                'tx_ntaimark_status' => $status->value,
                'tx_ntaimark_c2pa_state' => $c2pa->value,
            ]);
        }

        // A file that is gone must not be counted or offered for review.
        $files->insert('sys_file', [
            'uid' => 99,
            'pid' => 0,
            'storage' => 1,
            'identifier' => '/gone.jpg',
            'name' => 'gone.jpg',
            'mime_type' => 'image/jpeg',
            'missing' => 1,
        ]);
        $metadata->insert('sys_file_metadata', [
            'pid' => 0,
            'file' => 99,
            'tx_ntaimark_status' => AiStatus::Unreviewed->value,
        ]);
    }

    #[Test]
    public function theSummaryCountsReviewedOpenAndBrokenPerStorage(): void
    {
        $summaries = $this->subject->storageSummaries();

        self::assertCount(1, $summaries);
        $summary = $summaries[0];

        // Six present files; the missing one is not counted.
        self::assertSame(6, $summary->total);
        self::assertSame(1, $summary->unreviewed);
        self::assertSame(1, $summary->suggested);
        self::assertSame(2, $summary->getOpen());
        self::assertSame(4, $summary->getReviewed());
        self::assertSame(1, $summary->brokenC2pa);
    }

    /**
     * The guarantee behind the scan: a record a human has settled is never
     * offered up again, whatever flags the command is given.
     */
    #[Test]
    public function theScanOnlyOffersRecordsNobodyHasSettled(): void
    {
        $withoutForce = $this->subject->findFileUidsForScan();
        $withForce = $this->subject->findFileUidsForScan(includeSuggestions: true);

        self::assertSame([1], $withoutForce);

        sort($withForce);
        self::assertSame([1, 2], $withForce);

        // The confirmed ones — no AI, generated, modified, unknown origin.
        foreach ([3, 4, 5, 6] as $settled) {
            self::assertNotContains($settled, $withForce, sprintf('File %d was settled and must not be rescanned.', $settled));
        }
    }

    #[Test]
    public function aMissingFileIsNeverOfferedForScanning(): void
    {
        self::assertNotContains(99, $this->subject->findFileUidsForScan(includeSuggestions: true));
    }

    #[Test]
    public function onlyFilesCarryingASignatureAreOfferedForVerification(): void
    {
        $rows = $this->subject->findWithC2paState();

        $fileUids = array_map(static fn(array $row): int => (int) $row['file'], $rows);
        sort($fileUids);

        self::assertSame([4, 5], $fileUids);
    }

    #[Test]
    public function theWorkListCanBeFilteredByStatus(): void
    {
        $rows = $this->subject->findAssets([AiStatus::Generated->value]);

        self::assertCount(1, $rows);
        self::assertSame('file-4.jpg', $rows[0]['name']);
    }

    #[Test]
    public function anUnknownStatusInTheFilterIsIgnoredRatherThanQueried(): void
    {
        // 99 is not a status; the filter must fall back to "every status"
        // instead of returning nothing or reaching the database with it.
        self::assertSame(6, $this->subject->countAssets([99]));
    }

    #[Test]
    public function theCountMatchesTheFilteredList(): void
    {
        $statuses = [AiStatus::Unreviewed->value, AiStatus::Suggested->value];

        self::assertSame(2, $this->subject->countAssets($statuses));
        self::assertCount(2, $this->subject->findAssets($statuses));
    }

    /**
     * Converters write a WebP and an AVIF next to every image. They show the
     * same picture, so a separate declaration says nothing new — but they do
     * treble the work list and drag the reviewed percentage down to a figure
     * that describes the converter rather than the review. On this project
     * that was 162 of 560 files.
     */
    #[Test]
    public function formatVariantsOfAnImageStayOutOfTheReview(): void
    {
        $this->givenFile(20, '/photo.jpg.webp');
        $this->givenFile(21, '/photo.jpg.avif');
        $this->givenFile(22, '/photo.png.webp');

        $identifiers = array_column($this->subject->findAssets(), 'identifier');

        self::assertNotContains('/photo.jpg.webp', $identifiers);
        self::assertNotContains('/photo.jpg.avif', $identifiers);
        self::assertNotContains('/photo.png.webp', $identifiers);
    }

    /**
     * The distinction is the second extension. Somebody uploading a WebP has
     * uploaded a picture like any other, and leaving it out of the review
     * would be a gap in exactly the evidence this module produces.
     */
    #[Test]
    public function anUploadedWebpIsNotMistakenForAFormatVariant(): void
    {
        $this->givenFile(23, '/holiday.webp');
        $this->givenFile(24, '/holiday.avif');

        $identifiers = array_column($this->subject->findAssets(), 'identifier');

        self::assertContains('/holiday.webp', $identifiers);
        self::assertContains('/holiday.avif', $identifiers);
    }

    /**
     * The figures at the top of the module have to describe the same set of
     * files as the list below them.
     */
    #[Test]
    public function theFiguresCountTheSameFilesAsTheList(): void
    {
        $this->givenFile(25, '/photo.jpg.webp');

        $counted = 0;

        foreach ($this->subject->storageSummaries() as $summary) {
            $counted += $summary->total;
        }

        self::assertSame($this->subject->countAssets(), $counted);
        self::assertSame($counted, array_sum($this->subject->statusDistribution()));
    }

    /**
     * Only file types the extension can say something about belong in the
     * review.
     *
     * A positive list, not a list of exclusions: on this project the non-media
     * in the list were YAML, XML, HTML, empty files, a stylesheet and a script
     * — naming the last two would have caught two of eighteen, and the next
     * type would need naming too.
     */
    #[Test]
    public function onlyMediaAndDocumentsAreUpForReview(): void
    {
        $this->givenFile(30, '/theme.css', 'text/css');
        $this->givenFile(31, '/app.js', 'application/javascript');
        $this->givenFile(32, '/form.yaml', 'application/yaml');
        $this->givenFile(33, '/broken', 'inode/x-empty');
        $this->givenFile(34, '/clip.mp4', 'video/mp4');
        $this->givenFile(35, '/voice.mp3', 'audio/mpeg');
        $this->givenFile(36, '/report.pdf', 'application/pdf');
        $this->givenFile(37, '/logo.svg', 'image/svg+xml');

        $identifiers = array_column($this->subject->findAssets(), 'identifier');

        foreach (['/theme.css', '/app.js', '/form.yaml', '/broken'] as $ignored) {
            self::assertNotContains($ignored, $identifiers);
        }

        foreach (['/clip.mp4', '/voice.mp3', '/report.pdf', '/logo.svg'] as $reviewed) {
            self::assertContains($reviewed, $identifiers, $reviewed . ' has to stay in the review.');
        }
    }

    private function givenFile(int $uid, string $identifier, string $mimeType = 'image/webp'): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connectionPool->getConnectionForTable('sys_file')->insert('sys_file', [
            'uid' => $uid,
            'pid' => 0,
            'storage' => 1,
            'identifier' => $identifier,
            'name' => ltrim($identifier, '/'),
            'mime_type' => $mimeType,
            'missing' => 0,
        ]);
        $connectionPool->getConnectionForTable('sys_file_metadata')->insert('sys_file_metadata', [
            'pid' => 0,
            'file' => $uid,
            'tx_ntaimark_status' => AiStatus::Unreviewed->value,
        ]);
    }

    /**
     * Storage 0 is a storage like any other — FAL puts files that live outside
     * every configured storage there, and on this project that is a quarter of
     * the whole file base. It can therefore not double as the "no filter"
     * value, or those files could never be filtered to.
     */
    #[Test]
    public function storageZeroCanBeFilteredToLikeAnyOtherStorage(): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connectionPool->getConnectionForTable('sys_file')->insert('sys_file', [
            'uid' => 7,
            'pid' => 0,
            'storage' => 0,
            'identifier' => '/outside.jpg',
            'name' => 'outside.jpg',
            'mime_type' => 'image/jpeg',
            'missing' => 0,
        ]);
        $connectionPool->getConnectionForTable('sys_file_metadata')->insert('sys_file_metadata', [
            'pid' => 0,
            'file' => 7,
            'tx_ntaimark_status' => AiStatus::Unreviewed->value,
        ]);

        $inStorageZero = $this->subject->findAssets(storage: 0);

        self::assertCount(1, $inStorageZero);
        self::assertSame('outside.jpg', $inStorageZero[0]['name']);

        self::assertCount(6, $this->subject->findAssets(storage: 1));
        self::assertSame(7, $this->subject->countAssets(), 'The default has to mean every storage.');
    }
}
