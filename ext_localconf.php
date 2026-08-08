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

    // Labels audio and video without any template change. Images are not
    // claimed here — see the class comment for why.
    \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Resource\Rendering\RendererRegistry::class,
    )->registerRendererClass(
        \NetThinks\NtAimark\Resource\Rendering\MarkedMediaRenderer::class,
    );
})();
