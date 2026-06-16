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
        $treeBuilder = new TreeBuilder('theme_toolbox');

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
                ->arrayNode('editor')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('public_mode')
                            ->defaultFalse()
                            ->info('Run the live editor in public/demo mode (no server writes). Only set true on the public demo.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
