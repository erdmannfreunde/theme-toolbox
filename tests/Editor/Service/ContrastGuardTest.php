<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\Tests\Editor\Service;

use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\ContrastGuard;
use PHPUnit\Framework\TestCase;

class ContrastGuardTest extends TestCase
{
    private ContrastGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new ContrastGuard();
    }

    public function testLuminanceBounds(): void
    {
        $this->assertEqualsWithDelta(1.0, $this->guard->luminance('#ffffff'), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->guard->luminance('#000000'), 0.0001);
    }

    public function testRatio(): void
    {
        $this->assertEqualsWithDelta(21.0, $this->guard->ratio('#000000', '#ffffff'), 0.05);
        $this->assertEqualsWithDelta(1.0, $this->guard->ratio('#777777', '#777777'), 0.001);
    }

    /**
     * Shared colour-pair table — the JS port (public/contrast-guard.js) must
     * produce the same ratios (divergence guard, §8).
     *
     * @dataProvider colourPairs
     */
    public function testRatioTable(string $fg, string $bg, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, $this->guard->ratio($fg, $bg), 0.001);
    }

    /**
     * @return iterable<array{string, string, float}>
     */
    public static function colourPairs(): iterable
    {
        yield ['#000000', '#ffffff', 21.0];
        yield ['#ff5636', '#ffffff', 3.164374];
        yield ['#284456', '#ffffff', 10.236666];
        yield ['#777777', '#ffffff', 4.478089];
        yield ['#3a4a63', '#ffffff', 8.975930];
        yield ['#c08a3e', '#15302b', 4.663316];
    }

    public function testPasses(): void
    {
        $this->assertTrue($this->guard->passes('#222222', '#ffffff'));
        $this->assertFalse($this->guard->passes('#777777', '#ffffff'));
    }

    public function testNormalizeAndIsHex(): void
    {
        $this->assertSame('#ffffff', $this->guard->normalizeHex('#FFF'));
        $this->assertTrue($this->guard->isHex('#ABC'));
        $this->assertFalse($this->guard->isHex('#12'));
        $this->assertFalse($this->guard->isHex('notacolor'));
    }

    public function testAutoCorrectConvergesOnLightBackground(): void
    {
        $fixed = $this->guard->autoCorrect('#999999', '#ffffff');
        $this->assertTrue($this->guard->passes($fixed, '#ffffff'));
    }

    public function testAutoCorrectLightensOnDarkBackground(): void
    {
        $fixed = $this->guard->autoCorrect('#666666', '#111111');
        $this->assertTrue($this->guard->passes($fixed, '#111111'));
    }

    public function testCorrectPresetFixesTextButLeavesBrand(): void
    {
        $result = $this->guard->correctPreset([
            '--color-text' => '#aaaaaa',
            '--color-page-background' => '#ffffff',
            '--color-brand' => '#ff5636',
        ]);

        $this->assertTrue($result['corrected']);
        $this->assertTrue($this->guard->passes($result['preset']['--color-text'], '#ffffff'));
        $this->assertSame('#ff5636', $result['preset']['--color-brand']);
    }

    public function testCorrectPresetLeavesGoodValuesUnchanged(): void
    {
        $input = ['--color-text' => '#222222', '--color-page-background' => '#ffffff'];
        $result = $this->guard->correctPreset($input);

        $this->assertFalse($result['corrected']);
        $this->assertSame($input, $result['preset']);
    }

    public function testDerivePaletteIsAccessible(): void
    {
        $palette = $this->guard->derivePalette('#284456');

        $this->assertSame('#284456', $palette['--color-brand']);
        $this->assertTrue($this->guard->passes($palette['--color-text'], $palette['--color-page-background']));
    }
}
