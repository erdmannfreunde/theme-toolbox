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
use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\GoogleFontBridge;
use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\PresetApplier;
use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\TokenRegistry;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssCompiler;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class PresetApplierTest extends TestCase
{
    private string $tmp;

    private Filesystem $fs;

    private ContrastGuard $guard;

    private PresetApplier $applier;

    private string $customVariables;

    private GoogleFontBridge&MockObject $fontBridge;

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

        // Only the network boundary is stubbed; everything else is the real service.
        $this->fontBridge = $this->createMock(GoogleFontBridge::class);

        $this->applier = new PresetApplier($registry, $this->guard, $fileManager, $compiler, $this->fs, $this->fontBridge);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmp);
    }

    public function testSanitizeKeepsValidColour(): void
    {
        $result = $this->applier->sanitize(['--color-brand' => '#FF5636'], 'mytheme');
        $this->assertSame('#ff5636', $result['values']['--color-brand']);
    }

    public function testSanitizeClampsLength(): void
    {
        $result = $this->applier->sanitize(
            [
                '--base-font-size' => '5rem',
                '--base-border-radius' => '999px',
            ],
            'mytheme',
        );

        $this->assertSame('1.1875rem', $result['values']['--base-font-size']);
        $this->assertSame('24px', $result['values']['--base-border-radius']);
    }

    public function testSanitizeDropsInvalidAndUnknown(): void
    {
        $result = $this->applier->sanitize(
            [
                '--unknown' => '#123456',
                '--headings-font-weight' => '12345',
                '--color-text' => 'notacolor',
            ],
            'mytheme',
        );

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

    // ----------------------------------------------- Schriften der Vorlagen (#40)

    public function testApplyImportsTheFontFamilyOfAPreset(): void
    {
        $this->fontBridge
            ->expects($this->once())
            ->method('import')
            ->with('mytheme', 'Playfair Display')
            ->willReturn($this->importResult('Playfair Display'))
        ;

        $result = $this->applier->apply('mytheme', [
            '--base-font-family' => "'Playfair Display', Georgia, 'Times New Roman', serif",
        ]);

        $this->assertSame(['Playfair Display'], array_column($result['fonts']['imported'], 'family'));
        $this->assertSame([], $result['fonts']['failed']);
    }

    /**
     * Only the leading entry is a real choice — everything behind it is the
     * fallback chain and must never trigger a download.
     */
    public function testFallbackEntriesOfTheStackAreNotImported(): void
    {
        $seen = [];
        $this->fontBridge
            ->method('import')
            ->willReturnCallback(
                function (string $theme, string $family) use (&$seen): array {
                    $seen[] = $family;

                    return $this->importResult($family);
                },
            )
        ;

        $this->applier->apply('mytheme', [
            '--base-font-family' => "'Merriweather', 'Times New Roman', serif",
            '--headings-font-family' => "'Source Sans 3', 'Helvetica Neue', Helvetica, sans-serif",
        ]);

        $this->assertSame(['Merriweather', 'Source Sans 3'], $seen);
    }

    public function testSystemFontStackTriggersNoDownload(): void
    {
        $this->fontBridge
            ->expects($this->never())
            ->method('import')
        ;

        $result = $this->applier->apply('mytheme', [
            '--base-font-family' => "system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif",
        ]);

        $this->assertSame([], $result['fonts']['imported']);
        $this->assertSame([], $result['fonts']['failed']);
    }

    public function testTheSameFamilyOnTwoTokensIsImportedOnce(): void
    {
        $this->fontBridge
            ->expects($this->once())
            ->method('import')
            ->willReturn($this->importResult('Inter'))
        ;

        $this->applier->apply('mytheme', [
            '--base-font-family' => "'Inter', Helvetica, Arial, sans-serif",
            '--headings-font-family' => "'Inter', Helvetica, Arial, sans-serif",
        ]);
    }

    /**
     * Without server outbound the preset still has to land — the values are already
     * written at that point, the caller only learns which font is missing.
     */
    public function testFailedDownloadDoesNotBlockThePreset(): void
    {
        $this->fontBridge
            ->method('import')
            ->willThrowException(new \RuntimeException('no outbound'))
        ;

        $result = $this->applier->apply('mytheme', [
            '--base-font-family' => "'Nunito', Helvetica, sans-serif",
            '--color-text' => '#222222',
        ]);

        $this->assertSame(['Nunito'], $result['fonts']['failed']);
        $this->assertSame([], $result['fonts']['imported']);
        $this->assertStringContainsString('#222222', (string) file_get_contents($this->customVariables));
    }

    /**
     * A font import compiles the theme itself and the compiler memoises per request.
     * If the values were written after that, the compile at the end of apply() would
     * be a no-op and the preset would never reach the CSS.
     */
    public function testValuesAreWrittenBeforeTheFontImportCompiles(): void
    {
        $written = null;
        $this->fontBridge
            ->method('import')
            ->willReturnCallback(
                function (string $theme, string $family) use (&$written): array {
                    $written = file_exists($this->customVariables)
                        ? (string) file_get_contents($this->customVariables)
                        : null;

                    return $this->importResult($family);
                },
            )
        ;

        $this->applier->apply('mytheme', [
            '--base-font-family' => "'Inter', Helvetica, sans-serif",
            '--color-text' => '#333333',
        ]);

        $this->assertNotNull($written, 'Die Variablen wurden erst nach dem Font-Import geschrieben.');
        $this->assertStringContainsString('#333333', $written);
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

    /**
     * @return array{family: string, value: string, weights: list<string>, faces: list<array{weight: string, path: string}>}
     */
    private function importResult(string $family): array
    {
        $slug = strtolower(str_replace(' ', '-', $family));

        return [
            'family' => $family,
            'value' => \sprintf('"%s", sans-serif', $family),
            'weights' => ['400'],
            'faces' => [['weight' => '400', 'path' => 'fonts/'.$slug.'-400.woff2']],
        ];
    }

    private function customContent(): string
    {
        return (string) file_get_contents($this->customVariables);
    }
}
