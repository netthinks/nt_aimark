<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

/**
 * WCAG 2.x contrast maths.
 *
 * Pure arithmetic, no I/O — the part of the contrast decision that has to be
 * provably correct lives here on its own.
 *
 * @see https://www.w3.org/TR/WCAG22/#dfn-contrast-ratio
 */
final class ContrastCalculator
{
    /** Minimum ratio for non-text and large-text content at level AA. */
    public const MINIMUM_AA = 4.5;

    /** @var array{int, int, int} */
    public const BLACK = [0, 0, 0];

    /** @var array{int, int, int} */
    public const WHITE = [255, 255, 255];

    /**
     * @param array{int, int, int} $rgb
     */
    public function relativeLuminance(array $rgb): float
    {
        $channels = [];

        foreach ($rgb as $value) {
            $srgb = max(0, min(255, $value)) / 255;
            $channels[] = $srgb <= 0.04045
                ? $srgb / 12.92
                : (($srgb + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * @param array{int, int, int} $first
     * @param array{int, int, int} $second
     *
     * @return float Between 1.0 (identical) and 21.0 (black against white)
     */
    public function contrastRatio(array $first, array $second): float
    {
        $a = $this->relativeLuminance($first);
        $b = $this->relativeLuminance($second);

        $lighter = max($a, $b);
        $darker = min($a, $b);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * @param array{int, int, int} $foreground
     * @param array{int, int, int} $background
     */
    public function meetsAa(array $foreground, array $background): bool
    {
        return $this->contrastRatio($foreground, $background) >= self::MINIMUM_AA;
    }

    /**
     * Whether the white icon reads better than the black one on this
     * background. Ties go to black, which is the variant that also works on
     * the light plate.
     *
     * @param array{int, int, int} $background
     */
    public function prefersWhite(array $background): bool
    {
        return $this->contrastRatio(self::WHITE, $background)
            > $this->contrastRatio(self::BLACK, $background);
    }
}
