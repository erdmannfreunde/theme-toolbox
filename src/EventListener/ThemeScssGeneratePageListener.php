<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\LayoutModel;
use Contao\PageModel;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssCompiler;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;

#[AsHook('generatePage')]
class ThemeScssGeneratePageListener
{
    public function __construct(
        private readonly ThemeScssCompiler $compiler,
        private readonly ThemeScssFileManager $fileManager,
    ) {
    }

    public function __invoke(PageModel $pageModel, LayoutModel $layout): void
    {
        $entryFile = $layout->themeScss ?? '';

        if (!$entryFile) {
            return;
        }

        $themes = $this->fileManager->getAvailableThemes();

        if (empty($themes)) {
            return;
        }

        $themeName = array_key_first($themes);
        $cssPath = $this->compiler->getWebPath($themeName, $entryFile);

        if (!$cssPath) {
            return;
        }

        // Add the compiled CSS to the page
        $GLOBALS['TL_CSS'][] = $cssPath . '|static';

        // Also compile all other non-partial entry points so additional CSS files
        // (e.g. tinymce.css for the editor) are always available without having
        // to be selected as the layout entry point.
        foreach (array_keys($this->fileManager->getEntryPointFiles($themeName)) as $name) {
            if ($name === $entryFile) {
                continue;
            }

            $this->compiler->compile($themeName, $name);
        }
    }
}
