<?php

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener\DataContainer;

use Contao\DataContainer;

final class RegisterFieldsInPaletteListener
{
    public function onLoadContentCallback(DataContainer $dataContainer): void
    {
        foreach ($GLOBALS['TL_DCA']['tl_content']['palettes'] as $k => $palette) {
            if (!\is_array($palette) && false !== strpos($palette, 'cssID')) {
                $GLOBALS['TL_DCA']['tl_content']['palettes'][$k] = str_replace(
                    '{invisible_legend',
                    '{toolbox_legend},toolbox_classes;{invisible_legend',
                    $GLOBALS['TL_DCA']['tl_content']['palettes'][$k]
                );
            }
        }
    }
}