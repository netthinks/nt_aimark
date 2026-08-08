<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Service;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Service\ExifSignatureService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ExifSignatureServiceTest extends UnitTestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('exif_read_data') || !function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('ext-exif or GD is not available.');
        }

        $this->directory = sys_get_temp_dir() . '/nt-aimark-exif-' . uniqid('', true) . '/';
        mkdir($this->directory, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    /**
     * Builds a real JPEG with an APP1/EXIF segment carrying a Software tag.
     *
     * PHP can read EXIF but not write it, so the segment is assembled by hand.
     */
    private function jpegWithSoftware(string $software): string
    {
        $value = $software . "\0";
        $tiffHeader = 'II' . pack('v', 0x2A) . pack('V', 8);
        // 2 bytes entry count + 12 bytes entry + 4 bytes next-IFD pointer,
        // measured from the start of the TIFF header.
        $valueOffset = 8 + 2 + 12 + 4;
        $ifd = pack('v', 1)
            . pack('v', 0x0131)          // Software
            . pack('v', 2)               // ASCII
            . pack('V', strlen($value))
            . pack('V', $valueOffset)
            . pack('V', 0);
        $exif = "Exif\0\0" . $tiffHeader . $ifd . $value;
        $app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;

        $image = imagecreatetruecolor(8, 8);
        self::assertNotFalse($image);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        $path = $this->directory . 'image-' . uniqid('', true) . '.jpg';
        // SOI, then our segment, then the rest of the encoder's output.
        file_put_contents($path, "\xFF\xD8" . $app1 . substr($jpeg, 2));

        return $path;
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function knownGenerators(): array
    {
        return [
            'Midjourney' => ['Midjourney v6', 'Midjourney', 'Midjourney'],
            'DALL-E' => ['DALL-E 3', 'Dall-e', 'OpenAI'],
            'Firefly' => ['Adobe Firefly', 'Firefly', 'Adobe'],
            'Stable Diffusion' => ['Stable Diffusion XL', 'Stable diffusion', 'Stability AI'],
            'case is irrelevant' => ['MIDJOURNEY', 'Midjourney', 'Midjourney'],
        ];
    }

    #[Test]
    #[DataProvider('knownGenerators')]
    public function aKnownGeneratorSignatureIsFound(string $software, string $system, string $vendor): void
    {
        $result = (new ExifSignatureService())->read($this->jpegWithSoftware($software));

        self::assertSame($system, $result->system);
        self::assertSame($vendor, $result->vendor);
        self::assertSame('exif', $result->detectedBy);
    }

    /**
     * The weakest stage must also make the weakest claim: a tool name in EXIF
     * says a tool was involved, not that the whole image is synthetic.
     */
    #[Test]
    public function theSuggestionFromExifIsTheCautiousOne(): void
    {
        $result = (new ExifSignatureService())->read($this->jpegWithSoftware('Midjourney v6'));

        self::assertSame(AiStatus::Modified, $result->suggestedStatus);
    }

    #[Test]
    public function anOrdinaryCameraSignatureIsNotAFinding(): void
    {
        $result = (new ExifSignatureService())->read($this->jpegWithSoftware('Adobe Photoshop 26.0'));

        self::assertFalse($result->hasFinding());
    }

    #[Test]
    public function additionalSignaturesFromTheConfigurationAreHonoured(): void
    {
        $subject = new ExifSignatureService(['hausgenerator' => 'Meine GmbH']);

        $result = $subject->read($this->jpegWithSoftware('HausGenerator 2.1'));

        self::assertSame('Meine GmbH', $result->vendor);
    }

    #[Test]
    public function aFileWithoutExifYieldsNothing(): void
    {
        $path = $this->directory . 'plain.jpg';
        $image = imagecreatetruecolor(8, 8);
        self::assertNotFalse($image);
        imagejpeg($image, $path);
        imagedestroy($image);

        self::assertFalse((new ExifSignatureService())->read($path)->hasFinding());
    }

    #[Test]
    public function aMissingFileYieldsNothing(): void
    {
        self::assertFalse((new ExifSignatureService())->read($this->directory . 'nope.jpg')->hasFinding());
    }

    #[Test]
    public function aFileThatIsNotAnImageYieldsNothing(): void
    {
        $path = $this->directory . 'text.jpg';
        file_put_contents($path, 'not an image');

        self::assertFalse((new ExifSignatureService())->read($path)->hasFinding());
    }
}
