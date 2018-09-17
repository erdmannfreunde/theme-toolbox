<?php

/**
 * This file is part of erdmannfreunde/theme-utils.
 *
 * (c) 2018-2018 Erdmann & Freunde.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    erdmannfreunde/theme-utils
 * @author     Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright  2018-2018 Erdmann & Freunde.
 * @license    https://github.com/erdmannfreunde/theme-utils/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

$GLOBALS['TL_HOOKS']['replaceDynamicScriptTags'][] =
    ['erdmannfreunde.theme_utils.listener.disable_css_caching', 'onReplaceDynamicScriptTags'];
$GLOBALS['TL_HOOKS']['getSystemMessages'][]        =
    ['erdmannfreunde.theme_utils.listener.bypass_mode_alert_message', 'onGetSystemMessages'];

$GLOBALS['TL_MAINTENANCE'][] = \ErdmannFreunde\ThemeUtilsBundle\Backend\Maintenance\BypassScriptCache::class;
