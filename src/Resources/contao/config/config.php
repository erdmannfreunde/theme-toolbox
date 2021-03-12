<?php

/**
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) 2018-2018 Erdmann & Freunde.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    erdmannfreunde/theme-toolbox
 * @author     Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright  2018-2018 Erdmann & Freunde.
 * @license    https://github.com/erdmannfreunde/theme-toolbox/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

// Back end modules
$GLOBALS['BE_MOD']['design']['toolboxEditor'] = array('tables' => array('tl_toolbox_editor', 'tl_toolbox_editor_css'));

$GLOBALS['TL_HOOKS']['replaceDynamicScriptTags'][] =
    ['erdmannfreunde.theme_toolbox.listener.disable_css_caching', 'onReplaceDynamicScriptTags'];
$GLOBALS['TL_HOOKS']['getSystemMessages'][]        =
    ['erdmannfreunde.theme_toolbox.listener.bypass_mode_alert_message', 'onGetSystemMessages'];

array_unshift(
    $GLOBALS['TL_MAINTENANCE'],
    \ErdmannFreunde\ThemeToolboxBundle\Backend\Maintenance\BypassScriptCache::class
);
