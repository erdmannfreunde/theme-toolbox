<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener;

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\ServiceAnnotation\Hook;

/**
 * Add an error message when the bypass script mode is active.
 *
 * @Hook("getSystemMessages")
 */
class BypassModeAlertMessageListener
{
    public function __invoke(): string
    {
        $user = BackendUser::getInstance();

        if (!$user->hasAccess('maintenance', 'modules')) {
            return '';
        }

        if (Config::get('bypassScriptCache')) {
            return '<p class="tl_error">'.$GLOBALS['TL_LANG']['MSC']['bypassScriptCacheEnabled'].'</p>';
        }

        return '';
    }
}
