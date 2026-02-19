<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

// Palettes
PaletteManipulator::create()
    ->addField('headerClass', 'onload')
    ->applyToPalette('default', 'tl_layout')
;

PaletteManipulator::create()
    ->addField('footerClass', 'headerClass')
    ->applyToPalette('default', 'tl_layout')
;

// Fields

$GLOBALS['TL_DCA']['tl_layout']['fields']['headerClass'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_layout']['fields']['footerClass'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

// Theme Styles
PaletteManipulator::create()
    ->addLegend('theme_toolbox_legend', 'style_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('themeScss', 'theme_toolbox_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_layout')
;

$GLOBALS['TL_DCA']['tl_layout']['fields']['themeScss'] = [
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => [ErdmannFreunde\ThemeToolboxBundle\EventListener\DataContainer\LayoutThemeScssOptionsCallback::class, '__invoke'],
    'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];
