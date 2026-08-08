<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Adds missing labels to the rendered page as a last resort, for templates
 * that use neither the ViewHelpers nor the file renderer.
 *
 * Declared but not implemented in v1.0. An implementation must be idempotent:
 * images already labelled by the ViewHelpers must not be labelled twice.
 *
 * @api
 */
interface LabelInjectorInterface
{
    public function inject(string $html, ServerRequestInterface $request): string;
}
