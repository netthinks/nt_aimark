<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Functional\Service;

use NetThinks\NtAimark\Service\BadgeContrastService;
use NetThinks\NtAimark\Service\IconResolverService;
use NetThinks\NtAimark\Service\LabelRenderService;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Renders with an icon directory the test controls.
 *
 * The EU icon files are not in the repository — each installation downloads
 * them. Taking the service straight from the container therefore renders one
 * way on a machine where they have been downloaded and another way in CI,
 * which is how a test claiming to check the icon-less fallback came to pass
 * while actually rendering icons. Anything asserting on the presence or
 * absence of the graphic pins the directory instead of hoping.
 */
trait IconDirectoryTrait
{
    private string $emptyIconDirectory = '';

    private function rendererWithoutIcons(): LabelRenderService
    {
        return $this->rendererFor($this->iconDirectory());
    }

    /**
     * Stand-ins for the official artwork, so a test about the badge does not
     * depend on whether somebody downloaded the real files.
     */
    private function rendererWithIcons(): LabelRenderService
    {
        $directory = $this->iconDirectory();

        foreach (['basic', 'generated', 'modified'] as $variant) {
            foreach (['black', 'white'] as $colour) {
                file_put_contents(
                    $directory . 'ai-' . $variant . '-' . $colour . '.svg',
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'
                        . '<path fill="#fff" d="M0 0h10v10H0z"/></svg>',
                );
            }
        }

        return $this->rendererFor($directory);
    }

    private function iconDirectory(): string
    {
        if ($this->emptyIconDirectory === '') {
            $directory = sys_get_temp_dir() . '/nt-aimark-icons-' . uniqid('', true) . '/';
            mkdir($directory, 0o777, true);
            $this->emptyIconDirectory = $directory;
        }

        return $this->emptyIconDirectory;
    }

    private function rendererFor(string $directory): LabelRenderService
    {
        return new LabelRenderService(
            new IconResolverService($directory),
            $this->get(ViewFactoryInterface::class),
            $this->get(AssetCollector::class),
            $this->get(BadgeContrastService::class),
        );
    }

    private function removeEmptyIconDirectory(): void
    {
        if ($this->emptyIconDirectory !== '' && is_dir($this->emptyIconDirectory)) {
            foreach (glob($this->emptyIconDirectory . '*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($this->emptyIconDirectory);
        }

        $this->emptyIconDirectory = '';
    }
}
