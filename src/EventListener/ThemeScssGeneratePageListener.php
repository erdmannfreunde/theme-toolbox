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

#[AsHook('generatePage')]
class ThemeScssGeneratePageListener
{
    public function __construct(
        private readonly ThemeScssCompiler $compiler,
    ) {
    }

    public function __invoke(PageModel $pageModel, LayoutModel $layout): void
    {
        $themeName = $layout->themeScss ?? '';

        if (!$themeName) {
            return;
        }

        $cssPath = $this->compiler->getWebPath($themeName);

        if (!$cssPath) {
            return;
        }

        // Add the compiled CSS to the page
        $GLOBALS['TL_CSS'][] = $cssPath . '|static';
    }
}
