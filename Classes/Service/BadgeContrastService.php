<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\Model\BadgeContrast;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Resource\Exception;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Decides how the badge has to be drawn on a given image.
 *
 * The badge normally sits on an opaque plate, which guarantees contrast no
 * matter what is underneath. Only when the area behind it is measurably and
 * clearly dark or light does the plate come off — and then the icon colour is
 * chosen so it still clears 4.5:1 against that area.
 *
 * Every failure path leads back to the plate. A wrong guess here would make
 * the disclosure hard to see, which is exactly what must not happen.
 */
final readonly class BadgeContrastService
{
    /** Beyond this the decode cost is not worth a cosmetic improvement. */
    private const MAX_PIXELS = 30_000_000;

    /** Side length of the sampling grid over the badge area. */
    private const SAMPLES_PER_AXIS = 8;

    /** Share of the image edge the badge is assumed to cover. */
    private const BADGE_EXTENT = 0.25;

    public function __construct(
        private ContrastCalculator $contrastCalculator,
        private ?FrontendInterface $cache = null,
    ) {}

    public function resolve(FileInterface $file, string $position): BadgeContrast
    {
        $identifier = $this->cacheIdentifier($file, $position);

        if ($identifier !== null && $this->cache !== null) {
            $cached = $this->cache->get($identifier);
            if (is_array($cached) && isset($cached['white'], $cached['plate'])) {
                return new BadgeContrast((bool) $cached['white'], (bool) $cached['plate']);
            }
        }

        $contrast = $this->measure($file, $position);

        if ($identifier !== null && $this->cache !== null) {
            $this->cache->set(
                $identifier,
                ['white' => $contrast->useWhiteIcon, 'plate' => $contrast->needsPlate],
            );
        }

        return $contrast;
    }

    private function measure(FileInterface $file, string $position): BadgeContrast
    {
        $image = $this->loadImage($file);

        if ($image === null) {
            return BadgeContrast::guaranteed();
        }

        try {
            $samples = $this->sampleBadgeArea($image, $position);
        } finally {
            imagedestroy($image);
        }

        if ($samples === []) {
            return BadgeContrast::guaranteed();
        }

        $average = $this->average($samples);
        $useWhite = $this->contrastCalculator->prefersWhite($average);
        $foreground = $useWhite ? ContrastCalculator::WHITE : ContrastCalculator::BLACK;

        // A single busy corner can average out to a mid grey that clears the
        // threshold on paper while individual pixels do not. Every sample has
        // to hold, otherwise the plate stays.
        foreach ($samples as $sample) {
            if (!$this->contrastCalculator->meetsAa($foreground, $sample)) {
                return BadgeContrast::guaranteed();
            }
        }

        return new BadgeContrast(useWhiteIcon: $useWhite, needsPlate: false);
    }

    private function loadImage(FileInterface $file): ?\GdImage
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $path = $file->getForLocalProcessing(false);
        } catch (Exception | \RuntimeException) {
            return null;
        }

        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return null;
        }

        $size = @getimagesize($path);

        if ($size === false || $size[0] * $size[1] > self::MAX_PIXELS) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $image = @imagecreatefromstring($contents);

        return $image === false ? null : $image;
    }

    /**
     * @return list<array{int, int, int}>
     */
    private function sampleBadgeArea(\GdImage $image, string $position): array
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $areaWidth = max(1, (int) round($width * self::BADGE_EXTENT));
        $areaHeight = max(1, (int) round($height * self::BADGE_EXTENT));

        $left = str_contains($position, 'left') ? 0 : $width - $areaWidth;
        $top = str_contains($position, 'top') ? 0 : $height - $areaHeight;

        $samples = [];

        for ($x = 0; $x < self::SAMPLES_PER_AXIS; $x++) {
            for ($y = 0; $y < self::SAMPLES_PER_AXIS; $y++) {
                $pixelX = $left + (int) round($x * ($areaWidth - 1) / (self::SAMPLES_PER_AXIS - 1));
                $pixelY = $top + (int) round($y * ($areaHeight - 1) / (self::SAMPLES_PER_AXIS - 1));

                $colour = @imagecolorat($image, min($pixelX, $width - 1), min($pixelY, $height - 1));

                if ($colour === false) {
                    continue;
                }

                $samples[] = [($colour >> 16) & 0xFF, ($colour >> 8) & 0xFF, $colour & 0xFF];
            }
        }

        return $samples;
    }

    /**
     * @param list<array{int, int, int}> $samples
     *
     * @return array{int, int, int}
     */
    private function average(array $samples): array
    {
        $count = count($samples);
        $sum = [0, 0, 0];

        foreach ($samples as $sample) {
            $sum[0] += $sample[0];
            $sum[1] += $sample[1];
            $sum[2] += $sample[2];
        }

        return [
            (int) round($sum[0] / $count),
            (int) round($sum[1] / $count),
            (int) round($sum[2] / $count),
        ];
    }

    /**
     * Keyed by the file's content hash, so a replaced image is measured again
     * rather than reusing a stale decision.
     */
    private function cacheIdentifier(FileInterface $file, string $position): ?string
    {
        try {
            $sha1 = $file->getSha1();
        } catch (\Throwable) {
            return null;
        }

        if ($sha1 === '') {
            return null;
        }

        return sprintf('badge-%s-%s', $sha1, $position);
    }
}
