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

$GLOBALS['BE_MOD']['design']['toolboxEditor'] = ['tables' => ['tl_toolbox_editor', 'tl_toolbox_editor_css']];

array_unshift(
    $GLOBALS['TL_MAINTENANCE'],
    BypassScriptCache::class
);
