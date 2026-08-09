<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Middleware;

use NetThinks\NtAimark\Service\LabelInjectorInterface;
use NetThinks\NtAimark\Service\NullLabelInjector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Stream;

/**
 * The named place in the chain where finished HTML can still be labelled.
 *
 * The core package registers the position and passes through unchanged — a
 * second package supplies a LabelInjectorInterface and takes over, without
 * touching this class and without registering middleware of its own.
 *
 * Sits far out in the chain so it sees the complete response body.
 */
final readonly class LabelInjectionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LabelInjectorInterface $labelInjector,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Nothing registered: not worth reading the body at all.
        if ($this->labelInjector instanceof NullLabelInjector) {
            return $response;
        }

        if (!str_contains($response->getHeaderLine('Content-Type'), 'text/html')) {
            return $response;
        }

        $body = (string) $response->getBody();
        $processed = $this->labelInjector->apply($body, $request);

        if ($processed === $body) {
            return $response;
        }

        $stream = new Stream('php://temp', 'rw');
        $stream->write($processed);
        $stream->rewind();

        return $response->withBody($stream)->withoutHeader('Content-Length');
    }
}
