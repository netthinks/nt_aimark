<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

/**
 * Restores the XMP packet in a processed image.
 *
 * TYPO3 strips profiles from processed files by default, which takes the XMP
 * packet with it — measured, see Documentation/Metadata.md. Rather than turning
 * that off globally (it exists for good reasons), the packet is written back
 * into the derived file afterwards.
 *
 * A C2PA signature is deliberately NOT carried over. After rescaling it no
 * longer matches the pixels, and copying it would leave a derived file
 * carrying a cryptographic claim that it has been tampered with. Measurement
 * confirmed this: GraphicsMagick without the strip parameter does exactly
 * that, and the result validates as Invalid.
 *
 * JPEG only. Restoring XMP in PNG or WebP means rewriting container chunks,
 * which is a different job and not covered here.
 */
final readonly class MetadataPreservationService
{
    private const XMP_NAMESPACE = 'http://ns.adobe.com/xap/1.0/';

    /**
     * @return bool Whether the packet was restored
     */
    public function restoreXmp(string $sourcePath, string $targetPath): bool
    {
        if (!$this->isJpeg($sourcePath) || !$this->isJpeg($targetPath)) {
            return false;
        }

        $packet = $this->extractXmpSegment($sourcePath);

        if ($packet === null) {
            return false;
        }

        $target = @file_get_contents($targetPath);

        if ($target === false || !str_starts_with($target, "\xFF\xD8")) {
            return false;
        }

        // Already carries a packet — leave it alone rather than adding a second.
        if (str_contains($target, self::XMP_NAMESPACE)) {
            return false;
        }

        $rewritten = "\xFF\xD8" . $packet . substr($target, 2);

        return $this->writeIfStillAnImage($targetPath, $rewritten);
    }

    public function hasXmp(string $path): bool
    {
        return $this->extractXmpSegment($path) !== null;
    }

    /**
     * Walks the JPEG segment chain to the start of scan and returns the APP1
     * segment carrying the XMP packet.
     */
    private function extractXmpSegment(string $path): ?string
    {
        $contents = @file_get_contents($path);

        if ($contents === false || !str_starts_with($contents, "\xFF\xD8")) {
            return null;
        }

        $offset = 2;
        $length = strlen($contents);

        while ($offset + 4 <= $length && $contents[$offset] === "\xFF") {
            $marker = ord($contents[$offset + 1]);

            // Start of scan: image data begins, no metadata segments follow.
            if ($marker === 0xDA) {
                return null;
            }

            $size = unpack('n', substr($contents, $offset + 2, 2))[1] ?? 0;

            if ($size < 2 || $offset + 2 + $size > $length) {
                return null;
            }

            $segment = substr($contents, $offset, $size + 2);

            if ($marker === 0xE1 && str_contains($segment, self::XMP_NAMESPACE)) {
                return $segment;
            }

            $offset += $size + 2;
        }

        return null;
    }

    /**
     * Writes to a temporary file first and only replaces the target once the
     * result still parses as an image — a processed file that a browser cannot
     * display would be a far worse outcome than a missing XMP packet.
     */
    private function writeIfStillAnImage(string $targetPath, string $contents): bool
    {
        $temporary = $targetPath . '.ntaimark-tmp';

        if (@file_put_contents($temporary, $contents) === false) {
            return false;
        }

        if (@getimagesize($temporary) === false) {
            @unlink($temporary);

            return false;
        }

        if (!@rename($temporary, $targetPath)) {
            @unlink($temporary);

            return false;
        }

        return true;
    }

    private function isJpeg(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $info = @getimagesize($path);

        return is_array($info) && $info[2] === IMAGETYPE_JPEG;
    }
}
