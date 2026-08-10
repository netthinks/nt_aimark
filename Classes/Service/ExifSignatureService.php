<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Model\ProvenanceResult;

/**
 * Last-resort detection: looks for the name of a known generator in the EXIF
 * fields producers tend to fill in.
 *
 * This is the weakest of the three stages by a wide margin. It finds a *self
 * declaration* left behind by the producing tool — it does not analyse the
 * image. Nothing here ever becomes more than a suggestion, and a file without
 * a matching signature is never called AI-free on that basis.
 */
final readonly class ExifSignatureService
{
    /** Fields where generators leave their name. */
    private const FIELDS = ['Software', 'Credit', 'ImageDescription', 'Artist', 'Copyright'];

    /**
     * @var array<string, string> Needle in lower case => vendor
     */
    private const DEFAULT_SIGNATURES = [
        'midjourney' => 'Midjourney',
        'dall-e' => 'OpenAI',
        'dall·e' => 'OpenAI',
        'openai' => 'OpenAI',
        'firefly' => 'Adobe',
        'adobe firefly' => 'Adobe',
        'stable diffusion' => 'Stability AI',
        'stablediffusion' => 'Stability AI',
        'automatic1111' => 'Stability AI',
        'gemini' => 'Google',
        'imagen' => 'Google',
        'flux' => 'Black Forest Labs',
        'leonardo.ai' => 'Leonardo',
        'ideogram' => 'Ideogram',
    ];

    /**
     * @param array<string, string> $additionalSignatures
     */
    public function __construct(
        private array $additionalSignatures = [],
    ) {}

    public function read(string $absolutePath): ProvenanceResult
    {
        if (!function_exists('exif_read_data') || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return ProvenanceResult::nothing();
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $exif = @exif_read_data($absolutePath, 'IFD0', true);
        } catch (\Throwable) {
            $exif = false;
        } finally {
            libxml_use_internal_errors($previous);
        }

        if (!is_array($exif)) {
            return ProvenanceResult::nothing();
        }

        $haystack = $this->collectFields($exif);
        $match = $this->match($haystack);

        if ($match === null) {
            return ProvenanceResult::nothing();
        }

        return new ProvenanceResult(
            // Deliberately the weaker of the two: a self declaration in EXIF
            // says a tool was involved, not that the whole image is synthetic.
            suggestedStatus: AiStatus::Modified,
            system: $match['system'],
            vendor: $match['vendor'],
            createdAt: $this->createdAt($exif),
            detectedBy: 'exif',
        );
    }

    /**
     * @param array<string, mixed> $exif
     */
    private function collectFields(array $exif): string
    {
        $values = [];

        foreach ($exif as $section) {
            if (!is_array($section)) {
                continue;
            }

            foreach (self::FIELDS as $field) {
                if (isset($section[$field]) && is_string($section[$field])) {
                    $values[] = $section[$field];
                }
            }
        }

        return strtolower(implode(' | ', $values));
    }

    /**
     * @return array{system: string, vendor: string}|null
     */
    private function match(string $haystack): ?array
    {
        if ($haystack === '') {
            return null;
        }

        foreach ([...self::DEFAULT_SIGNATURES, ...$this->additionalSignatures] as $needle => $vendor) {
            $needle = strtolower(trim((string) $needle));

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return ['system' => ucfirst($needle), 'vendor' => $vendor];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $exif
     */
    private function createdAt(array $exif): int
    {
        foreach ($exif as $section) {
            if (!is_array($section)) {
                continue;
            }

            foreach (['DateTimeOriginal', 'DateTimeDigitized', 'DateTime'] as $field) {
                $value = $section[$field] ?? null;

                if (!is_string($value) || $value === '') {
                    continue;
                }

                // EXIF uses "2026:08:14 10:11:12", which DateTimeImmutable
                // does not understand as written.
                $normalised = preg_replace('/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', $value) ?? $value;

                try {
                    return (new \DateTimeImmutable($normalised))->getTimestamp();
                } catch (\Exception) {
                    continue;
                }
            }
        }

        return 0;
    }
}
