<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Service;

use NetThinks\NtAimark\Service\BadgeContrastService;
use NetThinks\NtAimark\Service\ContrastCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BadgeContrastServiceTest extends UnitTestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD is not available.');
        }

        $this->directory = sys_get_temp_dir() . '/nt-aimark-images-' . uniqid('', true) . '/';
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

    private function subject(): BadgeContrastService
    {
        return new BadgeContrastService(new ContrastCalculator());
    }

    /**
     * @param array{int, int, int} $rgb
     */
    private function solidImage(string $name, array $rgb): string
    {
        $image = imagecreatetruecolor(200, 200);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, max(0, min(255, $rgb[0])), max(0, min(255, $rgb[1])), max(0, min(255, $rgb[2]))));

        $path = $this->directory . $name . '.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * Dark on the left half, light on the right half.
     */
    private function splitImage(string $name): string
    {
        $image = imagecreatetruecolor(200, 200);
        self::assertNotFalse($image);
        imagefilledrectangle($image, 0, 0, 99, 199, (int) imagecolorallocate($image, 0, 0, 0));
        imagefilledrectangle($image, 100, 0, 199, 199, (int) imagecolorallocate($image, 255, 255, 255));

        $path = $this->directory . $name . '.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function fileFor(string $path): FileInterface
    {
        $file = $this->createStub(FileInterface::class);
        $file->method('getForLocalProcessing')->willReturn($path);
        $file->method('getSha1')->willReturn(is_file($path) ? (string) sha1_file($path) : '');

        return $file;
    }

    #[Test]
    public function aDarkAreaGetsTheWhiteIconWithoutAPlate(): void
    {
        $contrast = $this->subject()->resolve($this->fileFor($this->solidImage('black', [0, 0, 0])), 'bottom-right');

        self::assertTrue($contrast->useWhiteIcon);
        self::assertFalse($contrast->needsPlate);
        self::assertSame('plain', $contrast->cssModifier());
    }

    #[Test]
    public function aLightAreaGetsTheBlackIconWithoutAPlate(): void
    {
        $contrast = $this->subject()->resolve($this->fileFor($this->solidImage('white', [255, 255, 255])), 'bottom-right');

        self::assertFalse($contrast->useWhiteIcon);
        self::assertFalse($contrast->needsPlate);
    }

    /**
     * A uniform area never needs the plate: since 4.5 × 4.5 < 21, black and
     * white cannot both fail against the same colour. Mid grey lands on the
     * black icon at roughly 5.3:1.
     *
     * The plate therefore exists for areas that are not uniform, and for
     * images that cannot be measured at all.
     */
    #[Test]
    public function aUniformMidToneStillClearsTheThresholdWithOneOfTheTwoIcons(): void
    {
        $contrast = $this->subject()->resolve($this->fileFor($this->solidImage('grey', [128, 128, 128])), 'bottom-right');

        self::assertFalse($contrast->useWhiteIcon);
        self::assertFalse($contrast->needsPlate);
    }

    /**
     * The badge position has to steer which part of the image is measured —
     * otherwise the whole measurement is meaningless.
     *
     * @return array<string, array{string, bool}>
     */
    public static function positions(): array
    {
        return [
            'bottom-left sits on the dark half' => ['bottom-left', true],
            'top-left sits on the dark half' => ['top-left', true],
            'bottom-right sits on the light half' => ['bottom-right', false],
            'top-right sits on the light half' => ['top-right', false],
        ];
    }

    #[Test]
    #[DataProvider('positions')]
    public function thePositionDecidesWhichAreaIsMeasured(string $position, bool $expectWhiteIcon): void
    {
        $contrast = $this->subject()->resolve($this->fileFor($this->splitImage('split')), $position);

        self::assertSame($expectWhiteIcon, $contrast->useWhiteIcon);
        self::assertFalse($contrast->needsPlate);
    }

    /**
     * Every failure path has to end at the guaranteed variant.
     */
    #[Test]
    public function anUnreadableFileFallsBackToTheGuaranteedVariant(): void
    {
        $contrast = $this->subject()->resolve($this->fileFor($this->directory . 'does-not-exist.png'), 'bottom-right');

        self::assertTrue($contrast->needsPlate);
        self::assertFalse($contrast->useWhiteIcon);
    }

    #[Test]
    public function aFileThatIsNotAnImageFallsBackToTheGuaranteedVariant(): void
    {
        $path = $this->directory . 'not-an-image.png';
        file_put_contents($path, 'certainly not a PNG');

        $contrast = $this->subject()->resolve($this->fileFor($path), 'bottom-right');

        self::assertTrue($contrast->needsPlate);
    }

    /**
     * A busy corner can average out to a passable mid tone while individual
     * pixels fail. The plate has to stay in that case.
     */
    #[Test]
    public function aHighContrastPatternKeepsThePlate(): void
    {
        $image = imagecreatetruecolor(200, 200);
        self::assertNotFalse($image);
        $black = (int) imagecolorallocate($image, 0, 0, 0);
        $white = (int) imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);

        for ($x = 0; $x < 200; $x += 4) {
            for ($y = 0; $y < 200; $y += 4) {
                imagefilledrectangle($image, $x, $y, $x + 1, $y + 1, $black);
            }
        }

        $path = $this->directory . 'pattern.png';
        imagepng($image, $path);
        imagedestroy($image);

        self::assertTrue($this->subject()->resolve($this->fileFor($path), 'bottom-right')->needsPlate);
    }
}
