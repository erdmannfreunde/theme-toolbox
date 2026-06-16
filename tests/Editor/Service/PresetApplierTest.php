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
use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\PresetApplier;
use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\TokenRegistry;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssCompiler;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class PresetApplierTest extends TestCase
{
    private string $tmp;

    private Filesystem $fs;

    private ContrastGuard $guard;

    private PresetApplier $applier;

    private string $customVariables;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir().'/tt_apply_'.uniqid('', true);
        $this->fs = new Filesystem();
        $this->fs->mkdir($this->tmp.'/layout/mytheme/scss');
        file_put_contents($this->tmp.'/layout/mytheme/scss/default.scss', "@import 'variables';\n");
        file_put_contents(
            $this->tmp.'/layout/mytheme/scss/_variables.scss',
            ":root {\n  --color-brand: var(--brand-primary);\n  --color-text: #222222;\n}\n",
        );

        $this->customVariables = $this->tmp.'/layout/custom/scss/_variables.scss';

        $fileManager = new ThemeScssFileManager($this->tmp, $this->fs, 'layout', 'layout/custom');
        $registry = new TokenRegistry($fileManager);
        $compiler = new ThemeScssCompiler($fileManager, $this->tmp, $this->fs);
        $this->guard = new ContrastGuard();

        $this->applier = new PresetApplier($registry, $this->guard, $fileManager, $compiler, $this->fs);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmp);
    }

    private function customContent(): string
    {
        return (string) file_get_contents($this->customVariables);
    }

    public function testSanitizeKeepsValidColour(): void
    {
        $result = $this->applier->sanitize(['--color-brand' => '#FF5636'], 'mytheme');
        $this->assertSame('#ff5636', $result['values']['--color-brand']);
    }

    public function testSanitizeClampsLength(): void
    {
        $result = $this->applier->sanitize([
            '--base-font-size' => '5rem',
            '--base-border-radius' => '999px',
        ], 'mytheme');

        $this->assertSame('1.1875rem', $result['values']['--base-font-size']);
        $this->assertSame('24px', $result['values']['--base-border-radius']);
    }

    public function testSanitizeDropsInvalidAndUnknown(): void
    {
        $result = $this->applier->sanitize([
            '--unknown' => '#123456',
            '--headings-font-weight' => '12345',
            '--color-text' => 'notacolor',
        ], 'mytheme');

        $this->assertArrayNotHasKey('--unknown', $result['values']);
        $this->assertArrayNotHasKey('--headings-font-weight', $result['values']);
        $this->assertArrayNotHasKey('--color-text', $result['values']);
    }

    public function testReplacesValueInPlace(): void
    {
        $this->applier->apply('mytheme', ['--color-brand' => '#111111']);

        $content = $this->customContent();

        $this->assertStringContainsString('--color-brand: #111111;', $content);
        $this->assertStringNotContainsString('var(--brand-primary)', $content, 'the original value is replaced');
        $this->assertSame(1, substr_count($content, '--color-brand:'), 'no duplicate declaration');
        $this->assertStringNotContainsString('toolbox-editor', $content, 'no separate managed block');
    }

    public function testLeavesUntouchedTokensAlone(): void
    {
        // Only --color-brand changes; --color-text must keep its declared value.
        $this->applier->apply('mytheme', ['--color-brand' => '#111111']);

        $this->assertStringContainsString('--color-text: #222222;', $this->customContent());
    }

    public function testAddsAbsentPropertyToRootBlock(): void
    {
        $this->applier->apply('mytheme', ['--base-border-radius' => '12px']);

        $content = $this->customContent();
        $this->assertStringContainsString('--base-border-radius: 12px;', $content);
        $this->assertStringContainsString(':root {', $content);
    }

    public function testApplyAutoCorrectsLowContrast(): void
    {
        $result = $this->applier->apply('mytheme', [
            '--color-text' => '#bbbbbb',
            '--color-page-background' => '#ffffff',
        ]);

        $this->assertTrue($result['corrected']);
        $this->assertTrue($this->guard->passes($result['values']['--color-text'], '#ffffff'));
        $this->assertStringContainsString('--color-text: '.$result['values']['--color-text'].';', $this->customContent());
    }
}
