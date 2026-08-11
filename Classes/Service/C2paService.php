<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\DigitalSourceType;
use NetThinks\NtAimark\Domain\Enum\C2paState;
use NetThinks\NtAimark\Domain\Model\ProvenanceResult;
use Symfony\Component\Process\Process;

/**
 * Reads C2PA / Content Credentials manifests through the external c2patool.
 *
 * The binary is optional. Everything that can go wrong — missing binary,
 * timeout, unreadable output — ends in C2paState::NotVerifiable and lets the
 * caller carry on with the other detection stages. No exception leaves here.
 *
 * Reading and verifying needs no signing certificate; only writing manifests
 * would, and this extension never writes any.
 *
 * The implementation of {@see C2paInspectorInterface} that ships with the core.
 * A second package can replace or decorate it where the binary cannot be
 * installed.
 */
final readonly class C2paService implements C2paInspectorInterface, C2paInspectorDescriptionInterface, C2paInspectorProbeInterface
{
    /** The spec caps the stored manifest at 64 kB. */
    public const MANIFEST_LIMIT = 65536;

    public function __construct(
        private string $binaryPath = 'c2patool',
        private int $timeout = 15,
    ) {}

    public function isAvailable(): bool
    {
        return $this->run(['--version'])['exitCode'] === 0;
    }

    /**
     * Für das lokale Werkzeug ist die Probe dieselbe Frage wie die
     * Verfügbarkeit: ein Aufruf ohne Netz, der nichts kostet.
     */
    public function probeReachable(): bool
    {
        return $this->isAvailable();
    }

    /**
     * Names the place, not a sentence: the status panel frames it, and a bare
     * path needs no translation — the same way the other findings show
     * `ext-exif` or a configuration key.
     */
    public function describeInspection(): string
    {
        return $this->binaryPath . ' (lokal)';
    }

    public function inspect(string $absolutePath): ProvenanceResult
    {
        if ($this->binaryPath === '' || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return ProvenanceResult::nothing(C2paState::NotVerifiable);
        }

        $process = $this->run([$absolutePath]);

        return $this->interpretReport($process['stdout'], $process['exitCode'], $process['stderr']);
    }

    /**
     * Turns one c2patool run into a result.
     *
     * Separate from the process handling so the mapping can be tested against
     * captured tool output without the binary being present.
     *
     * @internal
     */
    public function interpretReport(string $stdout, int $exitCode, string $stderr): ProvenanceResult
    {
        if ($exitCode !== 0) {
            // A non-zero exit covers three different situations and they only
            // differ in the message: no manifest at all, a file c2patool
            // cannot open, and an unsupported format. Only the first is a
            // statement about the file rather than about the tooling.
            return ProvenanceResult::nothing(
                str_contains($stderr, 'No claim found')
                    ? C2paState::None
                    : C2paState::NotVerifiable,
            );
        }

        $manifest = json_decode($stdout, true);

        if (!is_array($manifest)) {
            return ProvenanceResult::nothing(C2paState::NotVerifiable);
        }

        return $this->interpret($manifest, $stdout);
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function interpret(array $manifest, string $raw): ProvenanceResult
    {
        $active = $this->activeManifest($manifest);
        $sourceType = $active === null ? '' : $this->digitalSourceType($active);

        return new ProvenanceResult(
            suggestedStatus: $sourceType === '' ? null : DigitalSourceType::toStatus($sourceType),
            system: $active === null ? '' : $this->claimGenerator($active),
            sourceType: $sourceType,
            createdAt: $active === null ? 0 : $this->signedAt($active),
            c2paState: $this->state($manifest),
            c2paManifest: mb_strcut($raw, 0, self::MANIFEST_LIMIT),
            detectedBy: 'c2pa',
        );
    }

    /**
     * c2patool reports the overall outcome in `validation_state`.
     *
     * "Valid" means the manifest is intact but the signer is not in a
     * configured trust list; "Trusted" means both. Reporting the first as
     * broken would accuse an untampered file of being tampered with, so both
     * count as valid here.
     *
     * @param array<string, mixed> $manifest
     */
    private function state(array $manifest): C2paState
    {
        $state = $manifest['validation_state'] ?? null;

        if (!is_string($state)) {
            return C2paState::NotVerifiable;
        }

        return match (strtolower($state)) {
            'valid', 'trusted' => C2paState::Valid,
            'invalid' => C2paState::Broken,
            default => C2paState::NotVerifiable,
        };
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @return array<string, mixed>|null
     */
    private function activeManifest(array $manifest): ?array
    {
        $key = $manifest['active_manifest'] ?? null;
        $manifests = $manifest['manifests'] ?? null;

        if (!is_string($key) || !is_array($manifests) || !isset($manifests[$key]) || !is_array($manifests[$key])) {
            return null;
        }

        return $manifests[$key];
    }

    /**
     * @param array<string, mixed> $active
     */
    private function digitalSourceType(array $active): string
    {
        $assertions = $active['assertions'] ?? [];

        if (!is_array($assertions)) {
            return '';
        }

        foreach ($assertions as $assertion) {
            if (!is_array($assertion)) {
                continue;
            }

            $data = $assertion['data'] ?? [];

            if (!is_array($data)) {
                continue;
            }

            // c2pa.actions carries the source type per action; the digital
            // source type of the last generating action is what describes the
            // asset as a whole.
            foreach ($data['actions'] ?? [] as $action) {
                if (is_array($action) && is_string($action['digitalSourceType'] ?? null)) {
                    return $action['digitalSourceType'];
                }
            }

            if (is_string($data['digitalSourceType'] ?? null)) {
                return $data['digitalSourceType'];
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $active
     */
    private function claimGenerator(array $active): string
    {
        $info = $active['claim_generator_info'] ?? null;

        if (is_array($info)) {
            $first = reset($info);
            if (is_array($first) && is_string($first['name'] ?? null)) {
                return $first['name'];
            }
        }

        $generator = $active['claim_generator'] ?? null;

        if (!is_string($generator) || $generator === '') {
            return '';
        }

        // "make_test_images/0.12.0 c2pa-rs/0.12.0" — the leading token is the
        // producing application, the rest is library noise.
        $first = explode(' ', $generator)[0];

        return str_replace('_', ' ', explode('/', $first)[0]);
    }

    /**
     * @param array<string, mixed> $active
     */
    private function signedAt(array $active): int
    {
        $time = $active['signature_info']['time'] ?? null;

        if (!is_string($time) || $time === '') {
            return 0;
        }

        try {
            return (new \DateTimeImmutable($time))->getTimestamp();
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function run(array $arguments): array
    {
        try {
            // Array form, so nothing is handed to a shell.
            $process = new Process([$this->binaryPath, ...$arguments]);
            $process->setTimeout((float) $this->timeout);
            $process->run();

            return [
                'exitCode' => (int) $process->getExitCode(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
            ];
        } catch (\Throwable) {
            // A missing binary must not take the upload down with it.
            return ['exitCode' => -1, 'stdout' => '', 'stderr' => ''];
        }
    }
}
