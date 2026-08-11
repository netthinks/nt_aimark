<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

/**
 * Says where a signature check actually happens.
 *
 * Optional companion to {@see C2paInspectorInterface}. Without it the system
 * status can only report that some check is possible — and "possible" covers
 * two situations an operator must be able to tell apart: the file is read on
 * their own server, or it is sent somewhere else to be read.
 *
 * For an extension whose subject is transparency, leaving that unsaid would be
 * the wrong kind of quiet. Whoever answers for the site should be able to see
 * it in the module rather than having to read a configuration file.
 *
 * @api Part of the published extension surface.
 */
interface C2paInspectorDescriptionInterface
{
    /**
     * Names the place a check happens, short enough for one line.
     *
     * A place, not a sentence: `c2patool (lokal)`, `c2pa.example.org
     * (Pruefdienst)`. The status panel supplies the framing text, so this
     * needs no translation — the same way other findings show `ext-exif` or a
     * configuration key unchanged.
     *
     * Where files leave the server, the address belongs in here. That is the
     * whole point of the method.
     */
    public function describeInspection(): string;
}
