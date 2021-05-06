<?php

use ErdmannFreunde\ThemeToolboxBundle\EventListener\DataContainer\RegisterFieldsInPaletteListener;

$GLOBALS['TL_DCA']['tl_article']['config']['onload_callback'][] =
    [RegisterFieldsInPaletteListener::class, 'onLoadContentCallback'];

$GLOBALS['TL_DCA']['tl_article']['fields']['toolbox_classes']['sql'] = 'text NULL';
