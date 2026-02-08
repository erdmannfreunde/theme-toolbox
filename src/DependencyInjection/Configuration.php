<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('erdmann_freunde_theme_toolbox');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('layout_dir')
                    ->defaultValue('layout')
                    ->info('Base directory for theme layouts (relative to project root)')
                ->end()
                ->scalarNode('custom_dir')
                    ->defaultValue('layout/custom')
                    ->info('Directory for custom SCSS overrides (relative to project root)')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
