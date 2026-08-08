<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Append-only record of every change to a declaration.
 *
 * The table is written and never updated or deleted through the application —
 * the point of the record is that it cannot be tidied up afterwards.
 */
final readonly class AuditService
{
    public const TABLE = 'tx_ntaimark_audit';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_AUTO_DETECT = 'auto_detect';
    public const SOURCE_NT_AI = 'nt_ai';
    public const SOURCE_NT_LINGUA = 'nt_lingua';
    public const SOURCE_CLI = 'cli';
    public const SOURCE_IMPORT = 'import';

    /** Keeps a runaway value from filling the log. */
    private const VALUE_LIMIT = 4096;

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function log(
        string $tableName,
        int $recordUid,
        string $action,
        string $source,
        string $fieldName = '',
        string|int|null $oldValue = null,
        string|int|null $newValue = null,
    ): void {
        $user = $this->backendUser();

        $this->connection()->insert(
            self::TABLE,
            [
                'pid' => 0,
                'tstamp' => time(),
                'table_name' => $tableName,
                'record_uid' => $recordUid,
                'be_user' => (int) ($user?->getUserId() ?? 0),
                // Denormalised on purpose: the record has to stay readable
                // after the backend user is deleted.
                'be_user_name' => (string) ($user?->getUserName() ?? ''),
                'action' => $action,
                'field_name' => $fieldName,
                'old_value' => $this->cap($oldValue),
                'new_value' => $this->cap($newValue),
                'source' => $source,
            ],
            [
                Connection::PARAM_INT,
                Connection::PARAM_INT,
                Connection::PARAM_STR,
                Connection::PARAM_INT,
                Connection::PARAM_INT,
                Connection::PARAM_STR,
                Connection::PARAM_STR,
                Connection::PARAM_STR,
                Connection::PARAM_STR,
                Connection::PARAM_STR,
                Connection::PARAM_STR,
            ],
        );
    }

    /**
     * Writes one entry per changed field.
     *
     * @param array<string, string|int|null> $before
     * @param array<string, string|int|null> $after
     */
    public function logChanges(
        string $tableName,
        int $recordUid,
        string $action,
        string $source,
        array $before,
        array $after,
    ): void {
        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? null;

            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            $this->log($tableName, $recordUid, $action, $source, $field, $oldValue, $newValue);
        }
    }

    private function cap(string|int|null $value): string
    {
        return mb_strcut((string) $value, 0, self::VALUE_LIMIT);
    }

    private function connection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE);
    }

    private function backendUser(): ?BackendUserAuthentication
    {
        $user = $GLOBALS['BE_USER'] ?? null;

        return $user instanceof BackendUserAuthentication ? $user : null;
    }
}
