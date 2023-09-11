<?php

use Composer\InstalledVersions;

if (
    InstalledVersions::isInstalled('contao/news-bundle') ||
    InstalledVersions::isInstalled('contao/calendar-bundle') ||
    InstalledVersions::isInstalled('contao/faq-bundle')
) {
    $GLOBALS['TL_DCA']['tl_module']['fields']['toolbox_classes']['sql'] = 'text NULL';
}