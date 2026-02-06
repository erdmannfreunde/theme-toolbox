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
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Contao\NewsBundle\ContaoNewsBundle;
use ErdmannFreunde\ThemeToolboxBundle\ErdmannFreundeThemeToolboxBundle;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Contao Manager plugin.
 */
class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    public function getBundles(ParserInterface $parser): array
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

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): RouteCollection
    {
        $file = '@ErdmannFreundeThemeToolboxBundle/config/routes.yaml';

        return $resolver->resolve($file)->load($file);
    }
}
