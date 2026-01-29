<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\ContaoManager;

use Composer\InstalledVersions;
use Contao\CalendarBundle\ContaoCalendarBundle;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\FaqBundle\ContaoFaqBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\NewsBundle\ContaoNewsBundle;
use ErdmannFreunde\ThemeToolboxBundle\ErdmannFreundeThemeToolboxBundle;

/**
 * Contao Manager plugin.
 */
class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser)
    {
        $dependencies[] = ContaoCoreBundle::class;

        if (InstalledVersions::isInstalled('contao/news-bundle')) {
            $dependencies[] = ContaoNewsBundle::class;
        }

        if (InstalledVersions::isInstalled('contao/calendar-bundle')) {
            $dependencies[] = ContaoCalendarBundle::class;
        }

        if (InstalledVersions::isInstalled('contao/faq-bundle')) {
            $dependencies[] = ContaoFaqBundle::class;
        }

        return [
            BundleConfig::create(ErdmannFreundeThemeToolboxBundle::class)
                ->setLoadAfter($dependencies),
        ];
    }
}
