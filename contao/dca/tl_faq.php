<?php

use Composer\InstalledVersions;

if (InstalledVersions::isInstalled('contao/faq-bundle')) {
    $GLOBALS['TL_DCA']['tl_faq']['fields']['toolbox_classes']['sql'] = 'text NULL';
    $GLOBALS['TL_DCA']['tl_faq']['fields']['toolbox_permissions'] = [
        'label' => &$GLOBALS['TL_LANG']['MSC']['eufThemeToolbox'],
        'exclude' => true,
        'sql' => "char(1) NOT NULL default ''",
    ];
}
