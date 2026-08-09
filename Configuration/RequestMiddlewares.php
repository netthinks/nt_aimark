<?php

declare(strict_types=1);

use NetThinks\NtAimark\Middleware\LabelInjectionMiddleware;

/**
 * Reserves a named place in the frontend chain for labelling finished HTML.
 *
 * The core package passes through unchanged; a second package supplies a
 * LabelInjectorInterface and takes over from there. Registering the position
 * here rather than leaving it to the other package keeps the ordering a
 * documented, stable part of this extension's API.
 *
 * Placed outside the time tracker so the complete response body is available
 * on the way back out.
 */
return [
    'frontend' => [
        'netthinks/nt-aimark/label-injection' => [
            'target' => LabelInjectionMiddleware::class,
            'after' => ['typo3/cms-frontend/timetracker'],
            'before' => ['typo3/cms-frontend/preview-simulator'],
        ],
    ],
];
