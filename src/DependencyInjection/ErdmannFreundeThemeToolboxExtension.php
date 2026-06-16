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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * This is the Bundle extension.
 */
class ErdmannFreundeThemeToolboxExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('theme_toolbox.layout_dir', $config['layout_dir']);
        $container->setParameter('theme_toolbox.custom_dir', $config['custom_dir']);
        // Pass the value through unchanged: a literal bool stays a bool, while an
        // %env(bool:…)% placeholder is preserved and resolved at runtime (a (bool)
        // cast here would turn the placeholder string into a constant true).
        $container->setParameter('theme_toolbox.editor.public_mode', $config['editor']['public_mode']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yml');
    }

    public function getAlias(): string
    {
        return 'theme_toolbox';
    }
}
