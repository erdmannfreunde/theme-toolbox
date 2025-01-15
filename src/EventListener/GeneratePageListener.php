<?php

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener;

use Contao\LayoutModel;
use Contao\PageModel;
use Contao\CoreBundle\ServiceAnnotation\Hook;

class GeneratePageListener
{
    /**
     * @Hook("generatePage")
     */
    public function onGeneratePage(PageModel $pageModel, LayoutModel $layoutModel, $pageRegular): void
    {
        // Klassen aus dem Layout holen
        $headerClass = $layoutModel->headerClass ?? '';
        $footerClass = $layoutModel->footerClass ?? '';

        // Template-Klassen setzen
        $pageRegular->Template->headerClass = $headerClass;
        $pageRegular->Template->footerClass = $footerClass;
    }
}
