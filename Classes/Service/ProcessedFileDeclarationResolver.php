<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\Model\AiDeclaration;
use NetThinks\NtAimark\Domain\Repository\DeclarationRepository;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Finds the declaration behind an image URL in rendered HTML.
 *
 * Needed by anything that works on the finished page rather than inside a
 * template: given `/fileadmin/_processed_/c/8/csm_photo_1a2b3c.jpg`, which
 * original file is that, and what was declared about it?
 *
 * Resolution goes through `sys_file_processedfile`, so it works for scaled
 * variants — which is the whole point, since that is what ends up in `src`.
 *
 * @api This is an extension point. It stays stable within a major version.
 */
final readonly class ProcessedFileDeclarationResolver
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private ResourceFactory $resourceFactory,
        private DeclarationRepository $declarationRepository,
    ) {}

    /**
     * @param string $path Identifier or public URL of a processed or original file
     */
    public function resolve(string $path): ?AiDeclaration
    {
        $identifier = $this->toIdentifier($path);

        if ($identifier === '') {
            return null;
        }

        $originalUid = $this->originalFileUid($identifier);

        if ($originalUid === 0) {
            return null;
        }

        try {
            return $this->declarationRepository->forFile($this->resourceFactory->getFileObject($originalUid));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Strips scheme, host and any query string, and removes the storage's
     * public prefix so the value matches what FAL stores.
     */
    private function toIdentifier(string $path): string
    {
        $path = (string) parse_url(trim($path), PHP_URL_PATH);

        if ($path === '') {
            return '';
        }

        $path = rawurldecode($path);

        foreach (['/fileadmin/', '/typo3temp/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return substr($path, strlen($prefix) - 1);
            }
        }

        return $path;
    }

    private function originalFileUid(string $identifier): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_processedfile');
        $queryBuilder->getRestrictions()->removeAll();

        $processed = $queryBuilder
            ->select('original')
            ->from('sys_file_processedfile')
            ->where($queryBuilder->expr()->eq(
                'identifier',
                $queryBuilder->createNamedParameter($identifier),
            ))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($processed !== false && (int) $processed > 0) {
            return (int) $processed;
        }

        // Not a processed variant — perhaps the original is referenced directly.
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $queryBuilder->getRestrictions()->removeAll();

        $original = $queryBuilder
            ->select('uid')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('identifier', $queryBuilder->createNamedParameter($identifier)),
                $queryBuilder->expr()->eq('missing', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return $original === false ? 0 : (int) $original;
    }
}
