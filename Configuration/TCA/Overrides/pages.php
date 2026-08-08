<?php

declare(strict_types=1);

use NetThinks\NtAimark\Configuration\TextTableRegistry;
use NetThinks\NtAimark\Configuration\TextTcaFactory;
use NetThinks\NtAimark\Service\ExtensionSettings;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') or die();

(static function (): void {
    $configured = '';

    try {
        $configured = GeneralUtility::makeInstance(ExtensionSettings::class)->additionalTextTables();
    } catch (\Throwable) {
        // Configuration is unavailable during early setup; the core tables
        // still get their fields.
    }

    foreach (TextTableRegistry::all($configured) as $table) {
        if (!isset($GLOBALS['TCA'][$table])) {
            continue;
        }

        ExtensionManagementUtility::addTCAcolumns($table, TextTcaFactory::columns());

        $GLOBALS['TCA'][$table]['palettes']['ntaimark_text'] = TextTcaFactory::palette();

        ExtensionManagementUtility::addToAllTCAtypes($table, TextTcaFactory::tabShowitem());
    }
})();
