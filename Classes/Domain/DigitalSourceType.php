<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain;

use NetThinks\NtAimark\Domain\Enum\AiStatus;

/**
 * IPTC DigitalSourceType vocabulary, as far as it concerns AI involvement.
 *
 * These URIs are the closest thing to a standardised, machine-readable
 * statement about how a piece of media came about.
 *
 * @see https://cv.iptc.org/newscodes/digitalsourcetype/
 */
final class DigitalSourceType
{
    public const TRAINED_ALGORITHMIC_MEDIA =
        'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia';

    public const COMPOSITE_WITH_TRAINED_ALGORITHMIC_MEDIA =
        'http://cv.iptc.org/newscodes/digitalsourcetype/compositeWithTrainedAlgorithmicMedia';

    public const ALGORITHMIC_MEDIA =
        'http://cv.iptc.org/newscodes/digitalsourcetype/algorithmicMedia';

    /**
     * The status this source type suggests, or null when the vocabulary term
     * says nothing about AI involvement (a plain photograph, for instance).
     */
    public static function toStatus(string $uri): ?AiStatus
    {
        // Producers differ on http/https and on trailing whitespace.
        $normalised = strtolower(trim($uri));
        $normalised = str_replace('https://', 'http://', $normalised);

        return match ($normalised) {
            strtolower(self::TRAINED_ALGORITHMIC_MEDIA),
            strtolower(self::ALGORITHMIC_MEDIA) => AiStatus::Generated,
            strtolower(self::COMPOSITE_WITH_TRAINED_ALGORITHMIC_MEDIA) => AiStatus::Modified,
            default => null,
        };
    }
}
