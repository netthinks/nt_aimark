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
    /**
     * Deliberately not called inject(): Symfony treats methods whose name
     * starts with "inject" as setter injection when autowiring is on, and the
     * container then refuses to build the service.
     */
    public function apply(string $html, ServerRequestInterface $request): string;
}
