<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\Backend\Maintenance;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Config;
use Contao\Controller;
use Contao\Environment;
use Contao\Input;
use Contao\MaintenanceModuleInterface;
use Contao\StringUtil;

/**
 * Class Maintenance.
 */
class BypassScriptCache extends Backend implements MaintenanceModuleInterface
{
    /**
     * Return true if the module is active.
     */
    public function isActive(): bool
    {
        return false;
    }

    /**
     * Generate the module.
     */
    public function run(): string
    {
        $formSubmit = 'tl_bypass_script_cache';

        $template = new BackendTemplate('be_maintenance_script_cache');

        $template->formSubmit = $formSubmit;
        $template->action = StringUtil::ampersand(Environment::get('request'));
        $template->headline = $GLOBALS['TL_LANG']['tl_maintenance']['bypassScriptCacheMode'];
        $template->isActive = $this->isActive();

        // Toggle the maintenance mode
        if ($formSubmit === Input::post('FORM_SUBMIT')) {
            Config::persist('bypassScriptCache', !Config::get('bypassScriptCache'));
            Controller::reload();
        }

        if (Config::get('bypassScriptCache')) {
            $template->class = 'tl_error';
            $template->explain = $GLOBALS['TL_LANG']['MSC']['bypassScriptCacheEnabled'];
            $template->submit = $GLOBALS['TL_LANG']['tl_maintenance']['bypassScriptCacheDisable'];
        } else {
            $template->class = 'tl_info';
            $template->submit = $GLOBALS['TL_LANG']['tl_maintenance']['bypassScriptCacheEnable'];
        }

        return $template->parse();
    }
}
