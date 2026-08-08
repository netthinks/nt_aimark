<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\EventListener;

use NetThinks\NtAimark\Configuration\TextTableRegistry;
use NetThinks\NtAimark\Service\ExtensionSettings;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\Event\AlterTableDefinitionStatementsEvent;

/**
 * Adds the text transparency columns to the tables a project configured.
 *
 * `pages` and `tt_content` are declared in ext_tables.sql; the additional ones
 * are only known at runtime, so their DDL is contributed here.
 */
#[AsEventListener(identifier: 'nt-aimark/alter-table-definitions')]
final readonly class AlterTableDefinitionListener
{
    public function __construct(
        private ExtensionSettings $settings,
    ) {}

    public function __invoke(AlterTableDefinitionStatementsEvent $event): void
    {
        foreach (TextTableRegistry::additional($this->settings->additionalTextTables()) as $table) {
            $event->addSqlData(sprintf(
                'CREATE TABLE %s (
                    tx_ntaimark_text_status tinyint(1) unsigned DEFAULT 0 NOT NULL,
                    tx_ntaimark_public_interest tinyint(1) unsigned DEFAULT 0 NOT NULL,
                    tx_ntaimark_editorial_control tinyint(1) unsigned DEFAULT 0 NOT NULL,
                    tx_ntaimark_responsible varchar(255) DEFAULT \'\' NOT NULL
                );',
                $table,
            ));
        }
    }
}
