<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Model;

/**
 * How the badge has to be drawn so it stays legible on this particular image.
 */
final readonly class BadgeContrast
{
    public function __construct(
        /** Use the white icon variant rather than the black one. */
        public bool $useWhiteIcon,
        /**
         * Draw an opaque plate behind the icon.
         *
         * True whenever the area behind the badge was not measurable or does
         * not on its own carry enough contrast. The plate is the guarantee;
         * dropping it is the exception, not the default.
         */
        public bool $needsPlate,
    ) {}

    /**
     * The safe answer: black icon on a light plate. Used whenever the image
     * cannot be measured.
     */
    public static function guaranteed(): self
    {
        return new self(useWhiteIcon: false, needsPlate: true);
    }

    public function cssModifier(): string
    {
        return $this->needsPlate ? 'plate' : 'plain';
    }
}
