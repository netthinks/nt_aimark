<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\Enum\IconVariant;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Burns the disclosure icon into the image file itself, so the label survives
 * being downloaded, screenshotted or re-shared outside the page.
 *
 * Declared but not implemented in v1.0. Any implementation has to write to a
 * derived file — never modify the original.
 *
 * @api
 */
interface IconCompositorInterface
{
    /**
     * @return string Absolute path of the derived file carrying the icon
     */
    public function composite(FileInterface $file, IconVariant $variant, string $position): string;
}
