<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Repository;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\C2paState;
use NetThinks\NtAimark\Domain\Model\StorageSummary;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Reads the state of the review for the backend module.
 *
 * All access through the QueryBuilder; the module shows counts across whole
 * storages, which is not something to assemble in PHP.
 */
final readonly class TransparencyRepository
{
    private const METADATA_TABLE = 'sys_file_metadata';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * One row per storage, so an editor sees where the work is.
     *
     * @return list<StorageSummary>
     */
    public function storageSummaries(): array
    {
        $queryBuilder = $this->queryBuilder();

        $rows = $queryBuilder
            ->select('f.storage')
            ->addSelectLiteral(
                'COUNT(*) AS total',
                sprintf(
                    'SUM(CASE WHEN m.tx_ntaimark_status = %d THEN 1 ELSE 0 END) AS unreviewed',
                    AiStatus::Unreviewed->value,
                ),
                sprintf(
                    'SUM(CASE WHEN m.tx_ntaimark_status = %d THEN 1 ELSE 0 END) AS suggested',
                    AiStatus::Suggested->value,
                ),
                sprintf(
                    'SUM(CASE WHEN m.tx_ntaimark_c2pa_state = %d THEN 1 ELSE 0 END) AS broken_c2pa',
                    C2paState::Broken->value,
                ),
            )
            ->from(self::METADATA_TABLE, 'm')
            ->join('m', 'sys_file', 'f', $queryBuilder->expr()->eq('f.uid', $queryBuilder->quoteIdentifier('m.file')))
            ->where($queryBuilder->expr()->eq('f.missing', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)))
            ->groupBy('f.storage')
            ->orderBy('f.storage')
            ->executeQuery()
            ->fetchAllAssociative();

        $names = $this->storageNames();
        $summaries = [];

        foreach ($rows as $row) {
            $storageUid = (int) $row['storage'];
            $summaries[] = new StorageSummary(
                storageUid: $storageUid,
                storageName: $names[$storageUid] ?? sprintf('Storage %d', $storageUid),
                total: (int) $row['total'],
                unreviewed: (int) $row['unreviewed'],
                suggested: (int) $row['suggested'],
                brokenC2pa: (int) $row['broken_c2pa'],
            );
        }

        return $summaries;
    }

    /**
     * The work list.
     *
     * @param list<int> $statuses Empty means every status
     *
     * @return list<array<string, mixed>>
     */
    public function findAssets(
        array $statuses = [],
        int $storage = -1,
        int $createdAfter = 0,
        int $createdBefore = 0,
        int $limit = 100,
        int $offset = 0,
    ): array {
        $queryBuilder = $this->buildAssetQuery($statuses, $storage, $createdAfter, $createdBefore);

        /** @var list<array<string, mixed>> $rows */
        $rows = $queryBuilder
            ->select(
                'm.uid',
                'm.file',
                'm.tx_ntaimark_status',
                'm.tx_ntaimark_c2pa_state',
                'm.tx_ntaimark_created_at',
                'm.tx_ntaimark_system',
                'f.name',
                'f.identifier',
                'f.storage',
                'f.mime_type',
            )
            ->orderBy('m.tx_ntaimark_status')
            ->addOrderBy('f.name')
            ->setMaxResults(max(1, $limit))
            ->setFirstResult(max(0, $offset))
            ->executeQuery()
            ->fetchAllAssociative();

        return $rows;
    }

    /**
     * @param list<int> $statuses
     */
    public function countAssets(
        array $statuses = [],
        int $storage = -1,
        int $createdAfter = 0,
        int $createdBefore = 0,
    ): int {
        return (int) $this->buildAssetQuery($statuses, $storage, $createdAfter, $createdBefore)
            ->count('m.uid')
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Metadata records by uid, for the bulk edit.
     *
     * @param list<int> $uids
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByUids(array $uids): array
    {
        $uids = array_values(array_filter(array_map(intval(...), $uids), static fn(int $uid): bool => $uid > 0));

        if ($uids === []) {
            return [];
        }

        $queryBuilder = $this->queryBuilder();

        $rows = $queryBuilder
            ->select('*')
            ->from(self::METADATA_TABLE)
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)))
            ->executeQuery()
            ->fetchAllAssociative();

        $byUid = [];

        foreach ($rows as $row) {
            $byUid[(int) $row['uid']] = $row;
        }

        return $byUid;
    }

    /**
     * File uids the scan should look at.
     *
     * Without $includeSuggestions only records nobody has looked at are
     * returned. Records a human has confirmed are never in this list — the
     * scan may not overrule a person, whatever flags it is given.
     *
     * @return list<int>
     */
    public function findFileUidsForScan(int $storage = -1, bool $includeSuggestions = false): array
    {
        $statuses = $includeSuggestions
            ? [AiStatus::Unreviewed->value, AiStatus::Suggested->value]
            : [AiStatus::Unreviewed->value];

        $queryBuilder = $this->buildAssetQuery($statuses, $storage, 0, 0);

        return array_values(array_map(
            intval(...),
            $queryBuilder->select('m.file')->executeQuery()->fetchFirstColumn(),
        ));
    }

    /**
     * Assets carrying a C2PA state, for revalidation.
     *
     * @return list<array<string, mixed>>
     */
    public function findWithC2paState(): array
    {
        $queryBuilder = $this->queryBuilder();

        /** @var list<array<string, mixed>> $rows */
        $rows = $queryBuilder
            ->select('m.uid', 'm.file', 'm.tx_ntaimark_c2pa_state', 'f.name', 'f.identifier')
            ->from(self::METADATA_TABLE, 'm')
            ->join('m', 'sys_file', 'f', $queryBuilder->expr()->eq('f.uid', $queryBuilder->quoteIdentifier('m.file')))
            ->where(
                $queryBuilder->expr()->eq('f.missing', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->neq(
                    'm.tx_ntaimark_c2pa_state',
                    $queryBuilder->createNamedParameter(C2paState::None->value, Connection::PARAM_INT),
                ),
            )
            ->orderBy('f.name')
            ->executeQuery()
            ->fetchAllAssociative();

        return $rows;
    }

    /**
     * @param list<int> $statuses
     */
    private function buildAssetQuery(array $statuses, int $storage, int $createdAfter, int $createdBefore): QueryBuilder
    {
        $queryBuilder = $this->queryBuilder();

        $queryBuilder
            ->from(self::METADATA_TABLE, 'm')
            ->join('m', 'sys_file', 'f', $queryBuilder->expr()->eq('f.uid', $queryBuilder->quoteIdentifier('m.file')))
            ->where($queryBuilder->expr()->eq('f.missing', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)));

        $statuses = array_values(array_filter(
            array_map(intval(...), $statuses),
            static fn(int $status): bool => AiStatus::tryFrom($status) !== null,
        ));

        if ($statuses !== []) {
            $queryBuilder->andWhere($queryBuilder->expr()->in(
                'm.tx_ntaimark_status',
                $queryBuilder->createNamedParameter($statuses, Connection::PARAM_INT_ARRAY),
            ));
        }

        // Storage 0 is a real storage — FAL puts files outside any configured
        // storage there — so "no filter" cannot be expressed as 0. It is -1.
        if ($storage >= 0) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq(
                'f.storage',
                $queryBuilder->createNamedParameter($storage, Connection::PARAM_INT),
            ));
        }

        // A zero creation date means "not recorded" and must not be swept into
        // a date range filter.
        if ($createdAfter > 0) {
            $queryBuilder->andWhere($queryBuilder->expr()->gte(
                'm.tx_ntaimark_created_at',
                $queryBuilder->createNamedParameter($createdAfter, Connection::PARAM_INT),
            ));
        }

        if ($createdBefore > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->lte(
                    'm.tx_ntaimark_created_at',
                    $queryBuilder->createNamedParameter($createdBefore, Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->gt(
                    'm.tx_ntaimark_created_at',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT),
                ),
            );
        }

        return $queryBuilder;
    }

    /**
     * @return array<int, string>
     */
    private function storageNames(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_storage');
        $queryBuilder->getRestrictions()->removeAll();

        $rows = $queryBuilder
            ->select('uid', 'name')
            ->from('sys_file_storage')
            ->executeQuery()
            ->fetchAllAssociative();

        $names = [];

        foreach ($rows as $row) {
            $names[(int) $row['uid']] = (string) $row['name'];
        }

        return $names;
    }

    private function queryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::METADATA_TABLE);
        // sys_file_metadata has no enable columns worth applying here; the
        // module is an inventory and must not hide records from the count.
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }
}
