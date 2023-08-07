<?php

use Composer\InstalledVersions;

if (InstalledVersions::isInstalled('contao/news-bundle')) {
    $GLOBALS['TL_DCA']['tl_news']['fields']['toolbox_classes']['sql'] = 'text NULL';
}
