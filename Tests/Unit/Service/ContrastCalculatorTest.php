<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Service;

use NetThinks\NtAimark\Service\ContrastCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ContrastCalculatorTest extends UnitTestCase
{
    private ContrastCalculator $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ContrastCalculator();
    }

    /**
     * Reference values from the WCAG definition of relative luminance.
     *
     * @return array<string, array{array{int, int, int}, float}>
     */
    public static function luminances(): array
    {
        return [
            'black' => [[0, 0, 0], 0.0],
            'white' => [[255, 255, 255], 1.0],
            'pure red' => [[255, 0, 0], 0.2126],
            'pure green' => [[0, 255, 0], 0.7152],
            'pure blue' => [[0, 0, 255], 0.0722],
        ];
    }

    /**
     * @param array{int, int, int} $rgb
     */
    #[Test]
    #[DataProvider('luminances')]
    public function relativeLuminanceFollowsTheWcagFormula(array $rgb, float $expected): void
    {
        self::assertEqualsWithDelta($expected, $this->subject->relativeLuminance($rgb), 0.0001);
    }

    #[Test]
    public function blackOnWhiteIsTheMaximumRatio(): void
    {
        self::assertEqualsWithDelta(
            21.0,
            $this->subject->contrastRatio(ContrastCalculator::BLACK, ContrastCalculator::WHITE),
            0.0001,
        );
    }

    #[Test]
    public function identicalColoursHaveNoContrast(): void
    {
        self::assertEqualsWithDelta(1.0, $this->subject->contrastRatio([120, 40, 90], [120, 40, 90]), 0.0001);
    }

    #[Test]
    public function theRatioDoesNotDependOnArgumentOrder(): void
    {
        $forwards = $this->subject->contrastRatio([10, 20, 30], [200, 210, 220]);
        $backwards = $this->subject->contrastRatio([200, 210, 220], [10, 20, 30]);

        self::assertSame($forwards, $backwards);
    }

    /**
     * @return array<string, array{array{int, int, int}, bool}>
     */
    public static function backgrounds(): array
    {
        return [
            'black background wants the white icon' => [[0, 0, 0], true],
            'white background wants the black icon' => [[255, 255, 255], false],
            'dark navy wants white' => [[16, 24, 64], true],
            'pale sand wants black' => [[240, 230, 200], false],
            // Mid grey is the ambiguous case; black wins ties because it also
            // works on the light plate.
            'mid grey falls to black' => [[119, 119, 119], false],
        ];
    }

    /**
     * @param array{int, int, int} $background
     */
    #[Test]
    #[DataProvider('backgrounds')]
    public function prefersWhitePicksTheHigherRatio(array $background, bool $expected): void
    {
        self::assertSame($expected, $this->subject->prefersWhite($background));
    }

    /**
     * The threshold is the point of the whole exercise, so it is pinned down
     * from both sides.
     */
    #[Test]
    public function meetsAaUsesTheFourAndAHalfThreshold(): void
    {
        // #767676 on white is the canonical 4.54:1 pass.
        self::assertTrue($this->subject->meetsAa([118, 118, 118], ContrastCalculator::WHITE));
        // One step lighter drops below the threshold.
        self::assertFalse($this->subject->meetsAa([120, 120, 120], ContrastCalculator::WHITE));
    }

    /**
     * The property the badge design rests on: against any single colour, at
     * least one of black and white clears AA. It holds because the worst case
     * is a colour that splits the 21:1 range evenly, and 4.5 × 4.5 < 21.
     *
     * This is why the opaque plate is only needed for areas that are *not*
     * uniform — and why dropping it elsewhere is safe.
     */
    #[Test]
    public function oneOfBlackOrWhiteAlwaysClearsAaAgainstAnySolidColour(): void
    {
        for ($value = 0; $value <= 255; $value += 1) {
            /** @var array{int, int, int} $colour */
            $colour = [$value, $value, $value];

            self::assertTrue(
                $this->subject->meetsAa(ContrastCalculator::BLACK, $colour)
                    || $this->subject->meetsAa(ContrastCalculator::WHITE, $colour),
                sprintf('Neither icon colour works on grey %d.', $value),
            );
        }

        // Also across the hue range, not just greys.
        /** @var list<array{int, int, int}> $hues */
        $hues = [[255, 0, 0], [0, 255, 0], [0, 0, 255], [255, 255, 0], [0, 255, 255], [255, 0, 255]];

        foreach ($hues as $colour) {
            self::assertTrue(
                $this->subject->meetsAa(ContrastCalculator::BLACK, $colour)
                    || $this->subject->meetsAa(ContrastCalculator::WHITE, $colour),
            );
        }
    }

    #[Test]
    public function channelValuesOutsideTheByteRangeAreClamped(): void
    {
        self::assertSame(
            $this->subject->relativeLuminance([255, 255, 255]),
            $this->subject->relativeLuminance([300, 999, 260]),
        );
        self::assertSame(
            $this->subject->relativeLuminance([0, 0, 0]),
            $this->subject->relativeLuminance([-5, -1, -100]),
        );
    }
}
