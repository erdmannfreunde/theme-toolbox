<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_DCA']['tl_content']['fields']['toolbox_classes']['sql'] = 'text NULL';
$GLOBALS['TL_DCA']['tl_content']['fields']['toolbox_permissions'] = [
    'label' => &$GLOBALS['TL_LANG']['MSC']['eufThemeToolbox'],
    'exclude' => true,
    'sql' => "char(1) NOT NULL default ''",
];
