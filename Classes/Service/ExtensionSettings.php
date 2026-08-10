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
     * Whether automatically produced format variants are kept out of the
     * review.
     *
     * Converters write a second and third file next to every image
     * (`photo.jpg.webp`, `photo.jpg.avif`). They show the same picture as the
     * original, so a separate declaration would say nothing new — but they do
     * treble the work list and push the reviewed percentage down to a figure
     * that describes the converter rather than the review.
     */
    public function hideDerivedFormats(): bool
    {
        return (bool) (int) $this->get('hideDerivedFormats', '1');
    }

    /**
     * MIME types the review covers.
     *
     * A positive list rather than a list of exclusions: what the extension can
     * say something about is a short, stable set — pictures, sound, moving
     * images, documents — while the things that do not belong keep arriving in
     * new shapes. On this project the list of non-media in the review was
     * YAML, XML, HTML, empty files, a stylesheet and a script; excluding the
     * latter two by name would have caught two of eighteen.
     *
     * An empty setting means "every file", for installations that want it.
     *
     * @return list<string>
     */
    public function reviewedMimeTypes(): array
    {
        $raw = $this->get('reviewedMimeTypes', 'image/*,audio/*,video/*,application/pdf');

        return array_values(array_filter(
            array_map(trim(...), explode(',', $raw)),
            static fn(string $type): bool => $type !== '',
        ));
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
