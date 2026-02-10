<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

use ErdmannFreunde\ThemeToolboxBundle\Backend\Maintenance\BypassScriptCache;

// Create 'themeToolbox' section right after 'design'
$offset = array_search('design', array_keys($GLOBALS['BE_MOD']));
if (false !== $offset) {
    $GLOBALS['BE_MOD'] = array_slice($GLOBALS['BE_MOD'], 0, $offset + 1, true)
        + ['themeToolbox' => []]
        + array_slice($GLOBALS['BE_MOD'], $offset + 1, null, true);
} else {
    $GLOBALS['BE_MOD']['themeToolbox'] = [];
}

$GLOBALS['BE_MOD']['themeToolbox']['toolboxEditor'] = ['tables' => ['tl_toolbox_editor', 'tl_toolbox_editor_css']];
$GLOBALS['BE_MOD']['themeToolbox']['themeFileEditor'] = [];
$GLOBALS['BE_MOD']['themeToolbox']['themeUpdate'] = [];

array_unshift(
    $GLOBALS['TL_MAINTENANCE'],
    BypassScriptCache::class,
);
