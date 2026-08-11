<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

/**
 * Answers whether a check would work *right now*.
 *
 * Deliberately separate from {@see C2paInspectorInterface::isAvailable()},
 * which must stay cheap: it is read on every module call and must never wait
 * on a network. This one may cost something — it is asked rarely, and the
 * caller caches the answer.
 *
 * Optional. Where it is missing, the status panel shows what is configured
 * and no more.
 *
 * @api Part of the published extension surface.
 */
interface C2paInspectorProbeInterface
{
    /**
     * Whether the check is reachable and answers.
     *
     * May take time and may go over the network, but must not throw: an
     * unreachable service is a `false`, not an exception. And it must not
     * send a media file — the question is whether the way works, not what it
     * would say about some file.
     */
    public function probeReachable(): bool;
}
