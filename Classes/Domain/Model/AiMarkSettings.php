<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Model;

/**
 * The site-level knobs the labelling reads.
 *
 * Deliberately a plain object rather than TYPO3's SiteSettings: the rule
 * service stays unit-testable without a site configuration, and the mapping
 * from the site set lives in one place.
 */
final readonly class AiMarkSettings
{
    public function __construct(
        /**
         * Whether assets of unknown origin are labelled.
         *
         * Off by default: "we do not know" is not the same claim as "AI was
         * involved", and the label would assert the latter.
         */
        public bool $labelUnknownOrigin = false,
        /**
         * Whether the file renderer labels audio and video automatically.
         *
         * Projects that already place the ViewHelpers in their own templates
         * switch this off so nothing gets labelled twice.
         */
        public bool $useFileRenderer = true,
        public bool $showDetails = true,
        public string $badgePosition = 'bottom-right',
        public string $badgeSize = 'medium',
    ) {}
}
