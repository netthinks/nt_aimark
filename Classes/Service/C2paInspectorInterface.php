<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\Model\ProvenanceResult;

/**
 * Reads Content Credentials out of a file.
 *
 * The core answers this with the local `c2patool`. That binary is linked
 * against glibc and needs a loader under /lib64, which plenty of managed hosts
 * do not have — no setting fixes that, so the reading has to be replaceable
 * rather than merely configurable.
 *
 * A second package can therefore register its own implementation, typically
 * decorating the built-in one and only stepping in where the binary is
 * missing. Two duties come with that:
 *
 * - **Never throw.** Everything that can go wrong — missing binary, timeout,
 *   unreachable service, nonsense response — ends in
 *   `C2paState::NotVerifiable`, and the caller carries on with the XMP and
 *   EXIF stages. A file must never fail to upload because a signature could
 *   not be checked.
 * - **Never state more than was verified.** The result may carry a *suggested*
 *   status; whether it becomes an assertion about the content stays a human
 *   decision.
 *
 * @api Part of the published extension surface. Do not change within a major
 *      version — see Documentation/Integration.md.
 */
interface C2paInspectorInterface
{
    /**
     * Whether an inspection can be carried out at all right now.
     *
     * Read by the system status panel, so it should answer without side
     * effects and reasonably fast; an implementation talking to a remote
     * service should not send a file to find out.
     */
    public function isAvailable(): bool;

    /**
     * @param string $absolutePath File to read. May be unreadable or absent —
     *                             that is a case to handle, not to fail on.
     */
    public function inspect(string $absolutePath): ProvenanceResult;
}
