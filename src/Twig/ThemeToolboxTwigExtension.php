<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\Twig;

use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssCompiler;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ThemeToolboxTwigExtension extends AbstractExtension
{
    private const ASSETS_DIR = 'assets';

    public function __construct(
        private readonly ThemeScssCompiler $compiler,
        private readonly ThemeScssFileManager $fileManager,
        private readonly string $projectDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_css', $this->getThemeCssPath(...)),
            new TwigFunction('theme_img', $this->getThemeImgPath(...)),
            new TwigFunction('theme_js', $this->getThemeJsPath(...)),
        ];
    }

    /**
     * Returns the web path for a compiled CSS file.
     *
     * Usage: {{ theme_css('default') }}
     */
    public function getThemeCssPath(string $entryFile = 'default'): ?string
    {
        $themeName = $this->getThemeName();

        if (!$themeName) {
            return null;
        }

        $webPath = $this->compiler->getWebPath($themeName, $entryFile);

        return $webPath ? '/' . $webPath : null;
    }

    /**
     * Returns the web path for a theme image file.
     *
     * Usage: {{ theme_img('logo.png') }}
     */
    public function getThemeImgPath(string $file): ?string
    {
        return $this->getAssetPath('img', $file);
    }

    /**
     * Returns the web path for a theme JavaScript file.
     *
     * Usage: {{ theme_js('main.js') }}
     */
    public function getThemeJsPath(string $file): ?string
    {
        return $this->getAssetPath('js', $file);
    }

    private function getAssetPath(string $type, string $file): ?string
    {
        $themeName = $this->getThemeName();

        if (!$themeName) {
            return null;
        }

        // Trigger compilation/sync to ensure assets are up to date
        $this->compiler->compile($themeName);

        $path = self::ASSETS_DIR . '/' . $themeName . '/' . $type . '/' . $file;

        if (!file_exists($this->projectDir . '/' . $path)) {
            return null;
        }

        return '/' . $path;
    }

    private function getThemeName(): ?string
    {
        $themes = $this->fileManager->getAvailableThemes();

        if (empty($themes)) {
            return null;
        }

        return array_key_first($themes);
    }
}
