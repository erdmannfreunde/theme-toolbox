<?php

use Composer\InstalledVersions;

if (InstalledVersions::isInstalled('contao/calendar-bundle')) {
    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['toolbox_classes']['sql'] = 'text NULL';
}
