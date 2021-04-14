<?php

use ErdmannFreunde\ThemeToolboxBundle\EventListener\DataContainer\RegisterFieldsInPaletteListener;

$GLOBALS['TL_DCA']['tl_form_field']['config']['onload_callback'][] =
    [RegisterFieldsInPaletteListener::class, 'onLoadContentCallback'];

$GLOBALS['TL_DCA']['tl_form_field']['fields']['toolbox_classes']['sql'] = 'text NULL';
