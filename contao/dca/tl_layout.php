<?php

declare(strict_types=1);

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
    ->applyToPalette('default', 'tl_layout');

PaletteManipulator::create()
    ->addField('footerClass', 'headerClass')
    ->applyToPalette('default', 'tl_layout');

 // Fields

$GLOBALS['TL_DCA']['tl_layout']['fields']['headerClass'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => array('maxlength'=>255, 'tl_class'=>'w50'),
    'sql' => "varchar(255) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_layout']['fields']['footerClass'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => array('maxlength'=>255, 'tl_class'=>'w50'),
    'sql' => "varchar(255) NOT NULL default ''"
];