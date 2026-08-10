<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Functional\EventListener;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\TextStatus;
use NetThinks\NtAimark\Event\AiContentGeneratedEvent;
use NetThinks\NtAimark\Service\AuditService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class AiContentGeneratedListenerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['netthinks/nt-aimark'];

    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = $this->get(EventDispatcherInterface::class);
        $this->givenRecords();
    }

    private function connection(string $table): \TYPO3\CMS\Core\Database\Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
    }

    private function givenRecords(): void
    {
        // A storage record needs a driver and its configuration. TYPO3 v14
        // tolerates a thinner record here, v13.4 does not and FAL then refuses
        // to hand out the file at all — so the thin version silently tested
        // nothing on v13.4.
        $this->connection('sys_file_storage')->insert('sys_file_storage', [
            'uid' => 1,
            'pid' => 0,
            'name' => 'Test',
            'driver' => 'Local',
            'configuration' => '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<T3FlexForms>
    <data>
        <sheet index="sDEF">
            <language index="lDEF">
                <field index="basePath"><value index="vDEF">fileadmin/</value></field>
                <field index="pathType"><value index="vDEF">relative</value></field>
            </language>
        </sheet>
    </data>
</T3FlexForms>',
            'is_default' => 1,
            'is_browsable' => 1,
            'is_public' => 1,
            'is_writable' => 1,
            'is_online' => 1,
        ]);

        // An untouched file, and one a person has already settled.
        foreach ([[1, AiStatus::Unreviewed], [2, AiStatus::NoAi]] as [$uid, $status]) {
            $this->connection('sys_file')->insert('sys_file', [
                'uid' => $uid,
                'pid' => 0,
                'storage' => 1,
                'identifier' => '/file-' . $uid . '.jpg',
                'name' => 'file-' . $uid . '.jpg',
                'mime_type' => 'image/jpeg',
                'missing' => 0,
            ]);
            $this->connection('sys_file_metadata')->insert('sys_file_metadata', [
                'pid' => 0,
                'file' => $uid,
                'tx_ntaimark_status' => $status->value,
            ]);
        }

        $this->connection('tt_content')->insert('tt_content', [
            'uid' => 5,
            'pid' => 1,
            'header' => 'Test',
            'tx_ntaimark_text_status' => TextStatus::NoAi->value,
        ]);
    }

    private function statusOfFile(int $fileUid): int
    {
        $row = $this->connection('sys_file_metadata')
            ->select(['tx_ntaimark_status'], 'sys_file_metadata', ['file' => $fileUid])
            ->fetchAssociative();

        return $row === false ? -1 : (int) $row['tx_ntaimark_status'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function auditEntries(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(AuditService::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        /** @var list<array<string, mixed>> $rows */
        $rows = $queryBuilder->select('*')->from(AuditService::TABLE)->orderBy('uid')->executeQuery()->fetchAllAssociative();

        return $rows;
    }

    /**
     * Even a first-hand report is a suggestion. Stating publicly that an image
     * is AI generated stays a human decision.
     *
     * @return array<string, array{bool, string}>
     */
    public static function reportedDegrees(): array
    {
        return [
            'fully generated' => [true, 'generated'],
            'AI assisted' => [false, 'modified'],
        ];
    }

    #[Test]
    #[DataProvider('reportedDegrees')]
    public function aReportedImageBecomesASuggestionAndNothingStronger(bool $fullyGenerated, string $icon): void
    {
        $this->dispatcher->dispatch(new AiContentGeneratedEvent(
            tableName: 'sys_file',
            recordUid: 1,
            aiSystem: 'DALL·E 3',
            aiVendor: 'OpenAI',
            contentKind: AiContentGeneratedEvent::KIND_IMAGE,
            fullyGenerated: $fullyGenerated,
            source: AuditService::SOURCE_NT_AI,
        ));

        self::assertSame(AiStatus::Suggested->value, $this->statusOfFile(1));

        $row = $this->connection('sys_file_metadata')
            ->select(['tx_ntaimark_icon', 'tx_ntaimark_system'], 'sys_file_metadata', ['file' => 1])
            ->fetchAssociative();

        self::assertIsArray($row);
        self::assertSame($icon, $row['tx_ntaimark_icon']);
        self::assertSame('DALL·E 3', $row['tx_ntaimark_system']);
    }

    #[Test]
    public function aRecordAPersonHasSettledIsNotOverwritten(): void
    {
        $this->dispatcher->dispatch(new AiContentGeneratedEvent(
            tableName: 'sys_file',
            recordUid: 2,
            aiSystem: 'Midjourney',
            aiVendor: 'Midjourney',
            contentKind: AiContentGeneratedEvent::KIND_IMAGE,
            fullyGenerated: true,
        ));

        self::assertSame(AiStatus::NoAi->value, $this->statusOfFile(2));
    }

    /**
     * The distinction that matters most here: an alt text describes a picture
     * and says nothing about how the picture came about. Treating it otherwise
     * would mark every image with an AI-written alt text as AI generated.
     */
    #[Test]
    public function aReportedAltTextLeavesTheImageStatusAlone(): void
    {
        $this->dispatcher->dispatch(new AiContentGeneratedEvent(
            tableName: 'sys_file',
            recordUid: 1,
            aiSystem: 'gpt-4o-mini',
            aiVendor: 'OpenAI',
            contentKind: AiContentGeneratedEvent::KIND_ALT_TEXT,
            fullyGenerated: true,
            source: AuditService::SOURCE_NT_AI,
        ));

        self::assertSame(AiStatus::Unreviewed->value, $this->statusOfFile(1));

        // But it is on record.
        $entries = $this->auditEntries();
        self::assertCount(1, $entries);
        self::assertSame('alt_text_generated', $entries[0]['action']);
    }

    /**
     * For text the reporting extension wrote the content, so this is knowledge
     * rather than a guess and lands as a fact.
     */
    #[Test]
    public function aReportedTextIsRecordedAsFact(): void
    {
        $this->dispatcher->dispatch(new AiContentGeneratedEvent(
            tableName: 'tt_content',
            recordUid: 5,
            aiSystem: 'gpt-4o-mini',
            aiVendor: 'OpenAI',
            contentKind: AiContentGeneratedEvent::KIND_TEXT,
            fullyGenerated: true,
            source: AuditService::SOURCE_NT_LINGUA,
        ));

        $row = $this->connection('tt_content')
            ->select(['tx_ntaimark_text_status', 'tx_ntaimark_public_interest'], 'tt_content', ['uid' => 5])
            ->fetchAssociative();

        self::assertIsArray($row);
        self::assertSame(TextStatus::AiGenerated->value, (int) $row['tx_ntaimark_text_status']);
        // And still produces no disclosure on its own — that needs a person to
        // mark it as a matter of public interest.
        self::assertSame(0, (int) $row['tx_ntaimark_public_interest']);
    }

    #[Test]
    public function aTranslationIsRecordedAsAiAssistedRatherThanGenerated(): void
    {
        $this->dispatcher->dispatch(new AiContentGeneratedEvent(
            tableName: 'tt_content',
            recordUid: 5,
            aiSystem: 'DeepL',
            aiVendor: 'DeepL',
            contentKind: AiContentGeneratedEvent::KIND_TEXT,
            fullyGenerated: false,
            source: AuditService::SOURCE_NT_LINGUA,
        ));

        $row = $this->connection('tt_content')
            ->select(['tx_ntaimark_text_status'], 'tt_content', ['uid' => 5])
            ->fetchAssociative();

        self::assertIsArray($row);
        self::assertSame(TextStatus::AiDraftRevised->value, (int) $row['tx_ntaimark_text_status']);
    }

    #[Test]
    public function theReportingExtensionIsNamedInTheTrail(): void
    {
        $this->dispatcher->dispatch(new AiContentGeneratedEvent(
            tableName: 'tt_content',
            recordUid: 5,
            aiSystem: 'DeepL',
            aiVendor: 'DeepL',
            contentKind: AiContentGeneratedEvent::KIND_TEXT,
            fullyGenerated: false,
            source: AuditService::SOURCE_NT_LINGUA,
        ));

        $entries = $this->auditEntries();

        self::assertCount(1, $entries);
        self::assertSame(AuditService::SOURCE_NT_LINGUA, $entries[0]['source']);
        self::assertSame('reported', $entries[0]['action']);
    }

    /**
     * The table name reaches SQL; a table that does not carry the fields must
     * not be written to.
     */
    #[Test]
    public function anUnknownTableIsIgnored(): void
    {
        $this->dispatcher->dispatch(new AiContentGeneratedEvent(
            tableName: 'be_users',
            recordUid: 1,
            aiSystem: 'x',
            aiVendor: 'x',
            contentKind: AiContentGeneratedEvent::KIND_TEXT,
            fullyGenerated: true,
        ));

        self::assertSame([], $this->auditEntries());
    }

    #[Test]
    public function aReportWithoutARecordIsIgnored(): void
    {
        $this->dispatcher->dispatch(new AiContentGeneratedEvent(
            tableName: 'sys_file',
            recordUid: 0,
            aiSystem: 'x',
            aiVendor: 'x',
            contentKind: AiContentGeneratedEvent::KIND_IMAGE,
            fullyGenerated: true,
        ));

        self::assertSame([], $this->auditEntries());
    }
}
