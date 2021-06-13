<?php

use ErdmannFreunde\ThemeToolboxBundle\EventListener\DataContainer\RegisterFieldsInPaletteListener;

$GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][] =
    [RegisterFieldsInPaletteListener::class, 'onLoadContentCallback'];

$GLOBALS['TL_DCA']['tl_content']['fields']['toolbox_classes']['sql'] = 'text NULL';
