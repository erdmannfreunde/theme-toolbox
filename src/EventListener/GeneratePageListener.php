<?php

declare(strict_types=1);

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\LayoutModel;
use Contao\PageModel;

#[AsHook('generatePage')]
class GeneratePageListener
{
    public function __invoke(PageModel $pageModel, LayoutModel $layoutModel, $pageRegular): void
    {
        // Klassen aus dem Layout holen
        $headerClass = $layoutModel->headerClass ?? '';
        $footerClass = $layoutModel->footerClass ?? '';

        // Template-Klassen setzen
        $pageRegular->Template->headerClass = $headerClass;
        $pageRegular->Template->footerClass = $footerClass;
    }
}
