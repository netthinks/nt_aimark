<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\Model\AiMarkSettings;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Maps the site set settings onto the object the labelling reads.
 *
 * Falls back to the defaults of AiMarkSettings when no site is available (CLI,
 * backend context) so nothing has to guard against a missing site.
 */
final readonly class AiMarkSettingsFactory
{
    private const POSITIONS = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];

    private const SIZES = ['small', 'medium', 'large'];

    public function fromRequest(?ServerRequestInterface $request): AiMarkSettings
    {
        $site = $request?->getAttribute('site');

        return $site instanceof Site ? $this->fromSite($site) : new AiMarkSettings();
    }

    public function fromSite(Site $site): AiMarkSettings
    {
        $settings = $site->getSettings();
        $defaults = new AiMarkSettings();

        return new AiMarkSettings(
            labelUnknownOrigin: (bool) $settings->get('ntAimark.labelUnknownOrigin', $defaults->labelUnknownOrigin),
            useFileRenderer: (bool) $settings->get('ntAimark.useFileRenderer', $defaults->useFileRenderer),
            showDetails: (bool) $settings->get('ntAimark.showDetails', $defaults->showDetails),
            // A stray value here would end up in a CSS class name, so anything
            // unexpected falls back instead of being passed through.
            badgePosition: $this->oneOf(
                (string) $settings->get('ntAimark.badgePosition', $defaults->badgePosition),
                self::POSITIONS,
                $defaults->badgePosition,
            ),
            badgeSize: $this->oneOf(
                (string) $settings->get('ntAimark.badgeSize', $defaults->badgeSize),
                self::SIZES,
                $defaults->badgeSize,
            ),
        );
    }

    /**
     * @param list<string> $allowed
     */
    private function oneOf(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
