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

use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\TokenRegistry;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class TokenRegistryTest extends TestCase
{
    private string $tmp;

    private Filesystem $fs;

    private TokenRegistry $registry;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir().'/tt_reg_'.uniqid('', true);
        $this->fs = new Filesystem();
        $this->fs->mkdir($this->tmp.'/layout/mytheme/scss/base');
        $this->fs->mkdir($this->tmp.'/layout/mytheme/presets');

        file_put_contents($this->tmp.'/layout/mytheme/scss/default.scss', 'html{color:red}');
        file_put_contents(
            $this->tmp.'/layout/mytheme/scss/base/_fonts.scss',
            "@font-face{font-family:'Inter';font-style:normal;font-weight:400;src:url('../fonts/inter/inter.woff2') format('woff2');}\n",
        );
        file_put_contents($this->tmp.'/layout/mytheme/tokens.json', json_encode([
            'groups' => [['key' => 'extra', 'label' => 'Extra']],
            'tokens' => [
                ['property' => '--hero-bg', 'group' => 'extra', 'label' => 'Hero BG', 'type' => 'color', 'default' => '#000000', 'core' => true],
                ['property' => '--color-brand', 'group' => 'colors', 'label' => 'Marke (Theme)', 'type' => 'color', 'default' => '#284456', 'core' => true],
                ['property' => '--headings-font-family', 'hidden' => true],
            ],
        ]));
        file_put_contents($this->tmp.'/layout/mytheme/presets/klarwerk.json', json_encode([
            'name' => 'Klarwerk',
            'description' => 'Anthrazit',
            'values' => ['--color-brand' => '#3a4a63', '--color-highlight' => '#c08a3e', '--color-text' => '#222831'],
        ]));

        $fileManager = new ThemeScssFileManager($this->tmp, $this->fs, 'layout', 'layout/custom');
        $this->registry = new TokenRegistry($fileManager);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmp);
    }

    public function testActiveTheme(): void
    {
        $this->assertSame('mytheme', $this->registry->getActiveTheme());
    }

    public function testBasisTokensPresent(): void
    {
        $properties = array_column($this->registry->toArray('mytheme')['tokens'], 'property');
        $this->assertContains('--color-text', $properties);
        $this->assertContains('--base-font-size', $properties);
    }

    public function testThemeTokensMerged(): void
    {
        $payload = $this->registry->toArray('mytheme');
        $properties = array_column($payload['tokens'], 'property');
        $groupKeys = array_column($payload['groups'], 'key');

        $this->assertContains('--hero-bg', $properties, 'theme-only token is merged in');
        $this->assertContains('extra', $groupKeys, 'theme group is merged in');
    }

    public function testThemeOverridesBasisToken(): void
    {
        $map = $this->registry->getTokenMap('mytheme');

        $this->assertSame('Marke (Theme)', $map['--color-brand']['label']);
        $this->assertSame('#284456', $map['--color-brand']['default']);
    }

    public function testHiddenTokenIsFilteredOut(): void
    {
        // The theme tokens.json hides the basis --headings-font-family token.
        $properties = array_column($this->registry->toArray('mytheme')['tokens'], 'property');

        $this->assertNotContains('--headings-font-family', $properties, 'hidden token is not in the mask');
        $this->assertArrayNotHasKey('--headings-font-family', $this->registry->getTokenMap('mytheme'), 'hidden token cannot be written');
        $this->assertContains('--base-font-family', $properties, 'non-hidden basis tokens stay');
    }

    public function testPresetsLoaded(): void
    {
        $presets = $this->registry->toArray('mytheme')['presets'];

        $this->assertCount(1, $presets);
        $this->assertSame('Klarwerk', $presets[0]['name']);
        $this->assertSame('#3a4a63', $presets[0]['values']['--color-brand']);
    }

    public function testFontsDiscovered(): void
    {
        $fonts = $this->registry->toArray('mytheme')['fonts'];
        $names = array_column($fonts, 'name');

        $this->assertSame('System', $fonts[0]['name'], 'system font is always first');
        $this->assertContains('Inter', $names, 'self-hosted font is discovered from _fonts.scss');
    }

    public function testSwatchesMapped(): void
    {
        $swatches = $this->registry->toArray('mytheme')['swatches'];

        $this->assertSame('--color-brand', $swatches['primary']);
        $this->assertSame('--color-text', $swatches['text']);
    }

    public function testMalformedThemeRegistryDegradesGracefully(): void
    {
        // A present-but-broken author file must not throw during page render.
        file_put_contents($this->tmp.'/layout/mytheme/tokens.json', '{ this is not valid json,, }');

        $registry = new TokenRegistry(new ThemeScssFileManager($this->tmp, $this->fs, 'layout', 'layout/custom'));
        $payload = $registry->toArray('mytheme');

        $properties = array_column($payload['tokens'], 'property');
        $this->assertContains('--color-brand', $properties, 'falls back to the basis tokens');
        $this->assertNotContains('--hero-bg', $properties, 'broken theme tokens are ignored');
    }
}
