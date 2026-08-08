<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\Model\AiMarkSettings;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Maps the site set settings onto the object the rules read.
 *
 * Falls back to the defaults of AiMarkSettings when no site is available (CLI,
 * backend context) so nothing has to guard against a missing site.
 */
final readonly class AiMarkSettingsFactory
{
    public function fromRequest(?ServerRequestInterface $request): AiMarkSettings
    {
        $site = $request?->getAttribute('site');

        return $site instanceof Site ? $this->fromSite($site) : new AiMarkSettings();
    }

    public function fromSite(Site $site): AiMarkSettings
    {
        $settings = $site->getSettings();

        return new AiMarkSettings(
            labelUnknownOrigin: (bool) $settings->get('ntAimark.labelUnknownOrigin', false),
        );
    }
}
