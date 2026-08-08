<?php

declare(strict_types=1);

use NetThinks\NtAimark\Backend\Controller\TransparencyModuleController;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Registers the "AI transparency" backend module.
 *
 * In TYPO3 v14 the toplevel module "web" was renamed to "content"
 * (Feature #107628). The parent is resolved at runtime so the module appears
 * in the right place on both supported versions — same approach as in nt_ai
 * and nt_supporttimes.
 */
$typo3Version = GeneralUtility::makeInstance(Typo3Version::class);
$contentParent = $typo3Version->getMajorVersion() >= 14 ? 'content' : 'web';

return [
    'content_ntaimark_transparency' => [
        'parent' => $contentParent,
        'position' => ['after' => 'web_list'],
        'access' => 'user',
        'workspaces' => 'live',
        'iconIdentifier' => 'nt-aimark-extension',
        'path' => '/module/content/nt-aimark-transparency',
        'labels' => 'LLL:EXT:nt_aimark/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => TransparencyModuleController::class . '::indexAction',
            ],
            'bulk' => [
                'path' => '/bulk',
                'target' => TransparencyModuleController::class . '::bulkAction',
                'methods' => ['POST'],
            ],
        ],
    ],
];
