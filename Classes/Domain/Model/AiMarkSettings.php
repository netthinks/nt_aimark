<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Model;

/**
 * The site-level knobs the labelling rules read.
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
    ) {}
}
