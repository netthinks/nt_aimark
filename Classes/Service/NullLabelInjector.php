<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The default: returns the page unchanged.
 *
 * Post-processing the finished HTML is provided by a separate package. The
 * core package labels through the ViewHelpers and the file renderer, which
 * covers projects whose templates one can edit — the fallback exists for
 * grown installations where that is not an option.
 */
final readonly class NullLabelInjector implements LabelInjectorInterface
{
    public function apply(string $html, ServerRequestInterface $request): string
    {
        return $html;
    }
}
