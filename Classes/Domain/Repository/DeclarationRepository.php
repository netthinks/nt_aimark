<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Repository;

use NetThinks\NtAimark\Domain\Model\AiDeclaration;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;

/**
 * Reads the declaration belonging to a FAL file.
 *
 * Deliberately goes through the metadata FAL already carries instead of
 * querying sys_file_metadata again — the frontend renders many images per page
 * and each one would otherwise cost a query.
 */
final readonly class DeclarationRepository
{
    public function forFile(FileInterface $file): AiDeclaration
    {
        $original = $this->resolveOriginal($file);

        if (!$original instanceof File || $original->getUid() <= 0) {
            return new AiDeclaration(tableName: 'sys_file_metadata', recordUid: 0);
        }

        return AiDeclaration::fromRecord($original->getMetaData()->get());
    }

    /**
     * Unwraps references and processed variants down to the file that actually
     * carries the metadata.
     */
    private function resolveOriginal(FileInterface $file): FileInterface
    {
        if ($file instanceof FileReference) {
            $file = $file->getOriginalFile();
        }

        if ($file instanceof ProcessedFile) {
            $file = $file->getOriginalFile();
        }

        return $file;
    }
}
