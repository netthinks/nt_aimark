<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Functional\Service;

use NetThinks\NtAimark\Domain\Enum\C2paState;
use NetThinks\NtAimark\Service\C2paService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Runs the real binary.
 *
 * The unit tests cover the mapping against captured output; this one checks
 * that the wiring around it — process call, exit code, stderr — still matches
 * how the installed tool behaves. Skipped where c2patool is not installed,
 * which is exactly the degradation the extension promises.
 */
final class C2paServiceIntegrationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['netthinks/nt-aimark'];

    private string $directory = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (!(new C2paService())->isAvailable()) {
            self::markTestSkipped('c2patool is not installed.');
        }

        $this->directory = sys_get_temp_dir() . '/nt-aimark-c2pa-' . uniqid('', true) . '/';
        mkdir($this->directory, 0o777, true);
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

    #[Test]
    public function anImageWithoutAManifestIsReportedAsHavingNone(): void
    {
        $path = $this->directory . 'plain.jpg';
        $image = imagecreatetruecolor(16, 16);
        self::assertNotFalse($image);
        imagejpeg($image, $path);
        imagedestroy($image);

        $result = (new C2paService())->inspect($path);

        // "No claim found" is a statement about the file, not about the
        // tooling, and has to be told apart from a failure to check.
        self::assertSame(C2paState::None, $result->c2paState);
        self::assertFalse($result->hasFinding());
    }

    #[Test]
    public function aFormatTheToolCannotReadIsNotVerifiableRatherThanUnmarked(): void
    {
        $path = $this->directory . 'notes.txt';
        file_put_contents($path, 'plain text, not a media file');

        self::assertSame(C2paState::NotVerifiable, (new C2paService())->inspect($path)->c2paState);
    }

    #[Test]
    public function aMissingBinaryDegradesInsteadOfFailing(): void
    {
        $path = $this->directory . 'plain2.jpg';
        $image = imagecreatetruecolor(16, 16);
        self::assertNotFalse($image);
        imagejpeg($image, $path);
        imagedestroy($image);

        $result = (new C2paService('/definitely/not/a/binary'))->inspect($path);

        self::assertSame(C2paState::NotVerifiable, $result->c2paState);
    }

    #[Test]
    public function availabilityIsDetectedForBothCases(): void
    {
        self::assertTrue((new C2paService())->isAvailable());
        self::assertFalse((new C2paService('/definitely/not/a/binary'))->isAvailable());
    }
}
