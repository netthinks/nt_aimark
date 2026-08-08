<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * The instance-wide settings, i.e. the ones that describe the server rather
 * than the site: where the external tooling is and how long it may take.
 */
final readonly class ExtensionSettings
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function c2patoolPath(): string
    {
        return trim($this->get('c2patoolPath', 'c2patool'));
    }

    public function c2patoolTimeout(): int
    {
        $timeout = (int) $this->get('c2patoolTimeout', '15');

        return $timeout > 0 ? $timeout : 15;
    }

    /**
     * Comma-separated list of further tables that carry the text fields.
     */
    public function additionalTextTables(): string
    {
        return $this->get('additionalTextTables', '');
    }

    /**
     * Whether the XMP packet is written back into processed images.
     */
    public function preserveMetadata(): bool
    {
        return (bool) (int) $this->get('preserveMetadata', '1');
    }

    /**
     * @return array<string, string> Needle => vendor
     */
    public function additionalExifSignatures(): array
    {
        $raw = $this->get('additionalExifSignatures', '');
        $signatures = [];

        foreach (explode(',', $raw) as $pair) {
            if (!str_contains($pair, '=')) {
                continue;
            }

            [$needle, $vendor] = explode('=', $pair, 2);
            $needle = trim($needle);

            if ($needle !== '') {
                $signatures[$needle] = trim($vendor);
            }
        }

        return $signatures;
    }

    private function get(string $key, string $default): string
    {
        try {
            $value = $this->extensionConfiguration->get('nt_aimark', $key);
        } catch (
            ExtensionConfigurationExtensionNotConfiguredException
            | ExtensionConfigurationPathDoesNotExistException
        ) {
            return $default;
        }

        return is_scalar($value) ? (string) $value : $default;
    }
}
