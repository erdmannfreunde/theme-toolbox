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
use Contao\Input;
use Contao\MaintenanceModuleInterface;

/**
 * Maintenance module to show or hide the frontend live theme editor.
 *
 * The editor is a design-phase tool; this switch lets an installation turn the
 * frontend dock off once the design is locked (and back on when needed). It is
 * off by default — the EditorOverlayListener only mounts the panel when
 * Config::get('frontendThemeEditor') is truthy.
 */
class FrontendThemeEditor extends Backend implements MaintenanceModuleInterface
{
    /**
     * Always false: ModuleMaintenance shows *only* the first active module and
     * hides all others, which is meant for exclusive states like the offline
     * maintenance mode. This is an ordinary toggle, so it must never take over
     * the maintenance screen — the on/off state is read from the config instead.
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
        $formSubmit = 'tl_frontend_theme_editor';
        $enabled = (bool) Config::get('frontendThemeEditor');

        // Apply the change in this same request and render the new state directly.
        // Going through Controller::reload() + re-reading the config showed the
        // previous state for one request (Turbo snapshot / config read timing), so the
        // posted target state is used as the value to render. The target is explicit
        // (not a blind toggle), which keeps a resubmit/reload idempotent.
        if ($formSubmit === Input::post('FORM_SUBMIT')) {
            $enabled = '1' === Input::post('frontendThemeEditorState');
            Config::persist('frontendThemeEditor', $enabled);
        }

        $template = new BackendTemplate('be_maintenance_theme_editor');

        $template->formSubmit = $formSubmit;
        $template->headline = $GLOBALS['TL_LANG']['tl_maintenance']['frontendThemeEditorMode'];
        // Always render the "inactive" wrapper class so the submit container is not
        // highlighted with a grey background (matches the former cache-bypass button);
        // the real on/off state is conveyed by the hint text and the button label.
        $template->isActive = false;
        // Value the button submits to flip the state (the desired new state).
        $template->targetState = $enabled ? '0' : '1';

        if ($enabled) {
            $template->class = 'tl_info';
            $template->explain = $GLOBALS['TL_LANG']['tl_maintenance']['frontendThemeEditorEnabled'];
            $template->submit = $GLOBALS['TL_LANG']['tl_maintenance']['frontendThemeEditorDisable'];
        } else {
            $template->class = 'tl_info';
            $template->submit = $GLOBALS['TL_LANG']['tl_maintenance']['frontendThemeEditorEnable'];
        }

        return $template->parse();
    }
}
