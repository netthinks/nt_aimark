<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\Enum\IconVariant;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * The default: burns nothing in and hands the file back unchanged.
 *
 * Burning the icon into the pixels is provided by a separate package. Nothing
 * here signals that something is missing — the visible label from the core
 * package is a complete answer on its own, and burning it in is an addition
 * for files that leave the website.
 */
final readonly class NullIconCompositor implements IconCompositorInterface
{
    public function composite(FileInterface $file, IconVariant $variant, string $position): string
    {
        return $file->getForLocalProcessing(false);
    }
}
