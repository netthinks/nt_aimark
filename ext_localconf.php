<?php

declare(strict_types=1);

defined('TYPO3') or die();

(static function (): void {
    // Measuring the area behind the badge means decoding the image. The result
    // only changes when the file changes, so it is worth keeping.
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ntaimark'] ??= [
        'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
        'backend' => \TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend::class,
        'groups' => ['pages'],
    ];

    // Records transparency changes made in the backend form. TYPO3 v14
    // dispatches no PSR-14 event for record updates, and the FAL event only
    // covers writes through the file API — a form save would leave no trace.
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['nt_aimark']
        = \NetThinks\NtAimark\DataHandling\MetaDataChangeHook::class;

    // Labels audio and video without any template change. Images are not
    // claimed here — see the class comment for why.
    \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Resource\Rendering\RendererRegistry::class,
    )->registerRendererClass(
        \NetThinks\NtAimark\Resource\Rendering\MarkedMediaRenderer::class,
    );
})();
