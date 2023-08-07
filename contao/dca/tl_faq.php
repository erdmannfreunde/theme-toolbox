<?php

use Composer\InstalledVersions;

if (InstalledVersions::isInstalled('contao/faq-bundle')) {
    $GLOBALS['TL_DCA']['tl_faq']['fields']['toolbox_classes']['sql'] = 'text NULL';
}
