<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Service;

use NetThinks\NtAimark\Service\MetadataPreservationService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class MetadataPreservationServiceTest extends UnitTestCase
{
    private string $directory = '';

    private MetadataPreservationService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD is not available.');
        }

        $this->directory = sys_get_temp_dir() . '/nt-aimark-meta-' . uniqid('', true) . '/';
        mkdir($this->directory, 0o777, true);
        $this->subject = new MetadataPreservationService();
    }

    protected function tearDown(): void
    {
        // Guard, not decoration: when setUp skips before assigning the
        // directory, an unguarded glob('' . '*') lists the *working directory*
        // and the unlink below deletes files from the repository.
        if ($this->directory === '' || !is_dir($this->directory)) {
            parent::tearDown();

            return;
        }

        foreach (glob($this->directory . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    private function jpeg(string $name, bool $withXmp): string
    {
        $image = imagecreatetruecolor(40, 30);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 90, 140, 200));
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        if ($withXmp) {
            $xmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
                . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
                . '<rdf:Description xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/">'
                . '<Iptc4xmpExt:DigitalSourceType>http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia</Iptc4xmpExt:DigitalSourceType>'
                . '</rdf:Description></rdf:RDF></x:xmpmeta>';
            $payload = "http://ns.adobe.com/xap/1.0/\0" . $xmp;
            $segment = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;
            $jpeg = "\xFF\xD8" . $segment . substr($jpeg, 2);
        }

        $path = $this->directory . $name . '.jpg';
        file_put_contents($path, $jpeg);

        return $path;
    }

    private function png(string $name): string
    {
        $image = imagecreatetruecolor(20, 20);
        self::assertNotFalse($image);
        $path = $this->directory . $name . '.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    #[Test]
    public function theXmpPacketIsRestoredInTheProcessedFile(): void
    {
        $source = $this->jpeg('source', withXmp: true);
        $processed = $this->jpeg('processed', withXmp: false);

        self::assertTrue($this->subject->restoreXmp($source, $processed));
        self::assertTrue($this->subject->hasXmp($processed));
        self::assertStringContainsString('trainedAlgorithmicMedia', (string) file_get_contents($processed));
    }

    /**
     * A processed file a browser cannot display would be a far worse outcome
     * than a missing packet.
     */
    #[Test]
    public function theProcessedFileStaysAValidImage(): void
    {
        $source = $this->jpeg('source', withXmp: true);
        $processed = $this->jpeg('processed', withXmp: false);

        $this->subject->restoreXmp($source, $processed);

        $info = getimagesize($processed);

        self::assertIsArray($info);
        self::assertSame(IMAGETYPE_JPEG, $info[2]);
    }

    /**
     * The central promise of this step: a C2PA signature no longer matches the
     * pixels after rescaling. Copying it would leave the derived file carrying
     * a cryptographic claim that it has been tampered with.
     */
    #[Test]
    public function aC2paManifestIsNeverCarriedOver(): void
    {
        $source = $this->jpeg('source', withXmp: true);
        // A JUMBF box is how C2PA rides along in a JPEG.
        file_put_contents($source, (string) file_get_contents($source) . 'jumbf c2pa manifest payload');

        $processed = $this->jpeg('processed', withXmp: false);
        $this->subject->restoreXmp($source, $processed);

        $result = (string) file_get_contents($processed);

        self::assertStringContainsString('trainedAlgorithmicMedia', $result);
        self::assertStringNotContainsString('c2pa manifest payload', $result);
    }

    #[Test]
    public function aSourceWithoutXmpChangesNothing(): void
    {
        $source = $this->jpeg('source', withXmp: false);
        $processed = $this->jpeg('processed', withXmp: false);
        $before = (string) file_get_contents($processed);

        self::assertFalse($this->subject->restoreXmp($source, $processed));
        self::assertSame($before, file_get_contents($processed));
    }

    /**
     * Processing sometimes leaves the packet in place. Adding a second one
     * would give the file two conflicting statements.
     */
    #[Test]
    public function anExistingPacketIsNotDuplicated(): void
    {
        $source = $this->jpeg('source', withXmp: true);
        $processed = $this->jpeg('processed', withXmp: true);

        self::assertFalse($this->subject->restoreXmp($source, $processed));
        self::assertSame(
            1,
            substr_count((string) file_get_contents($processed), 'http://ns.adobe.com/xap/1.0/'),
        );
    }

    /**
     * Restoring XMP in PNG or WebP means rewriting container chunks, which is
     * a different job. It has to decline rather than corrupt the file.
     */
    #[Test]
    public function formatsOtherThanJpegAreDeclined(): void
    {
        $source = $this->jpeg('source', withXmp: true);
        $processed = $this->png('processed');
        $before = (string) file_get_contents($processed);

        self::assertFalse($this->subject->restoreXmp($source, $processed));
        self::assertSame($before, file_get_contents($processed));
    }

    #[Test]
    public function missingFilesAreDeclinedRatherThanFailing(): void
    {
        $existing = $this->jpeg('source', withXmp: true);

        self::assertFalse($this->subject->restoreXmp($this->directory . 'nope.jpg', $existing));
        self::assertFalse($this->subject->restoreXmp($existing, $this->directory . 'nope.jpg'));
    }

    #[Test]
    public function aTruncatedSourceIsDeclinedRatherThanFailing(): void
    {
        $source = $this->directory . 'broken.jpg';
        file_put_contents($source, "\xFF\xD8\xFF\xE1\x00");
        $processed = $this->jpeg('processed', withXmp: false);

        self::assertFalse($this->subject->restoreXmp($source, $processed));
    }

    #[Test]
    public function noTemporaryFileIsLeftBehind(): void
    {
        $source = $this->jpeg('source', withXmp: true);
        $processed = $this->jpeg('processed', withXmp: false);

        $this->subject->restoreXmp($source, $processed);

        self::assertFileDoesNotExist($processed . '.ntaimark-tmp');
    }
}
