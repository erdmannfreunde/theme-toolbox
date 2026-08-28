<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\Tests\Twig;

use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssCompiler;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;
use ErdmannFreunde\ThemeToolboxBundle\Twig\ThemeToolboxTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ThemeToolboxTwigExtensionTest extends TestCase
{
    private string $tmp;

    private Filesystem $fs;

    private ThemeToolboxTwigExtension $extension;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir().'/tt_twig_'.uniqid('', true);
        $this->fs = new Filesystem();
        $this->fs->mkdir([
            $this->tmp.'/layout/mytheme/scss',
            $this->tmp.'/layout/mytheme/js',
            $this->tmp.'/layout/mytheme/img',
        ]);
        file_put_contents($this->tmp.'/layout/mytheme/scss/default.scss', "body {\n  color: #222222;\n}\n");
        file_put_contents($this->tmp.'/layout/mytheme/js/navigation.js', "console.log('nav');\n");
        file_put_contents($this->tmp.'/layout/mytheme/img/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->extension = $this->createExtension();
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmp);
    }

    public function testJsPathCarriesTheMtimeVersion(): void
    {
        $path = $this->extension->getThemeJsPath('navigation.js');

        $this->assertSame('/assets/mytheme/js/navigation.js?v='.$this->expectedVersion('assets/mytheme/js/navigation.js'), $path);
    }

    public function testImgPathCarriesTheMtimeVersion(): void
    {
        $path = $this->extension->getThemeImgPath('logo.svg');

        $this->assertSame('/assets/mytheme/img/logo.svg?v='.$this->expectedVersion('assets/mytheme/img/logo.svg'), $path);
    }

    public function testCssPathCarriesTheMtimeVersion(): void
    {
        $path = $this->extension->getThemeCssPath('default');

        $this->assertSame('/assets/mytheme/css/default.css?v='.$this->expectedVersion('assets/mytheme/css/default.css'), $path);
    }

    /**
     * Contao appends the same parameter to $GLOBALS['TL_CSS'] files, see
     * Contao\Template::generateStyleTag(). The format must not drift apart.
     *
     * @dataProvider assetPaths
     */
    public function testVersionFormatMatchesContao(string $method, string $file): void
    {
        $path = $this->extension->{$method}($file);

        $this->assertMatchesRegularExpression('#^/assets/mytheme/[a-z]+/[^?]+\?v=[0-9a-f]{8}$#', (string) $path);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function assetPaths(): iterable
    {
        yield ['getThemeJsPath', 'navigation.js'];
        yield ['getThemeImgPath', 'logo.svg'];
        yield ['getThemeCssPath', 'default'];
    }

    /**
     * The returned path must still resolve to a file once the query string is
     * stripped — existing templates rely on it being a valid asset path.
     *
     * @dataProvider assetPaths
     */
    public function testPathWithoutQueryStringStillPointsToTheFile(string $method, string $file): void
    {
        $path = (string) $this->extension->{$method}($file);

        $this->assertFileExists($this->tmp.strtok($path, '?'));
    }

    public function testVersionFollowsTheMtimeOfTheServedAsset(): void
    {
        $before = $this->extension->getThemeJsPath('navigation.js');

        // Age the sources so the compiler skips the recompile (and with it the
        // asset sync), then stamp the already synced asset with a known mtime.
        $this->ageSources();
        touch($this->tmp.'/assets/mytheme/js/navigation.js', 1700000000);
        clearstatcache();

        // A fresh instance, because the compiler caches compiled paths per request.
        $after = $this->createExtension()->getThemeJsPath('navigation.js');

        $this->assertNotSame($before, $after);
        $this->assertSame('/assets/mytheme/js/navigation.js?v='.substr(md5('1700000000'), 0, 8), $after);
    }

    public function testMissingAssetReturnsNull(): void
    {
        $this->assertNull($this->extension->getThemeJsPath('does-not-exist.js'));
        $this->assertNull($this->extension->getThemeImgPath('does-not-exist.png'));
    }

    public function testMissingEntryPointReturnsNull(): void
    {
        $this->assertNull($this->extension->getThemeCssPath('does-not-exist'));
    }

    public function testWithoutThemeAllFunctionsReturnNull(): void
    {
        $this->fs->remove($this->tmp.'/layout');
        $extension = $this->createExtension();

        $this->assertNull($extension->getThemeCssPath('default'));
        $this->assertNull($extension->getThemeJsPath('navigation.js'));
        $this->assertNull($extension->getThemeImgPath('logo.svg'));
    }

    private function ageSources(): void
    {
        $past = time() - 3600;

        touch($this->tmp.'/layout/mytheme/scss/default.scss', $past);
        touch($this->tmp.'/layout/mytheme/js/navigation.js', $past);
        touch($this->tmp.'/layout/mytheme/img/logo.svg', $past);
    }

    private function createExtension(): ThemeToolboxTwigExtension
    {
        $fileManager = new ThemeScssFileManager($this->tmp, $this->fs, 'layout', 'layout/custom');
        $compiler = new ThemeScssCompiler($fileManager, $this->tmp, $this->fs);

        return new ThemeToolboxTwigExtension($compiler, $fileManager, $this->tmp);
    }

    /**
     * The version Contao would generate for the given project-relative path.
     */
    private function expectedVersion(string $relativePath): string
    {
        clearstatcache(true, $this->tmp.'/'.$relativePath);

        return substr(md5((string) filemtime($this->tmp.'/'.$relativePath)), 0, 8);
    }
}
