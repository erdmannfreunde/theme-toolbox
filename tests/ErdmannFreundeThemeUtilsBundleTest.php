<?php

/**
 * This file is part of erdmannfreunde/theme-utils.
 *
 * (c) 2018-2018 Erdmann & Freunde.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    erdmannfreunde/theme-utils
 * @author     Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright  2018-2018 Erdmann & Freunde.
 * @license    https://github.com/erdmannfreunde/theme-utils/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

namespace ErdmannFreunde\ThemeUtilsBundle\Test;

use ErdmannFreunde\ThemeUtilsBundle\DependencyInjection\ErdmannFreundeThemeUtilsExtension;
use ErdmannFreunde\ThemeUtilsBundle\ErdmannFreundeThemeUtilsBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\ComposerResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ContaoBlackForestContaoSkeletonBundleTest
 *
 * @covers \ErdmannFreunde\ThemeUtilsBundle\ContaoBlackForestContaoSkeletonBundle
 */
class ErdmannFreundeThemeUtilsBundleTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        $bundle = new ErdmannFreundeThemeUtilsBundle();

        $this->assertInstanceOf(ErdmannFreundeThemeUtilsBundle::class, $bundle);
    }

    public function testReturnsTheContainerExtension()
    {
        $extension = (new ErdmannFreundeThemeUtilsBundle())->getContainerExtension();

        $this->assertInstanceOf(ErdmannFreundeThemeUtilsExtension::class, $extension);
    }

    /**
     * @covers \ErdmannFreunde\ThemeUtilsBundle\DependencyInjection\ErdmannFreundeThemeUtilsExtension::load
     */
    public function testLoadExtensionConfiguration()
    {
        $extension = (new ErdmannFreundeThemeUtilsBundle())->getContainerExtension();
        $container = new ContainerBuilder();

        $extension->load([], $container);

        $this->assertInstanceOf(ComposerResource::class, $container->getResources()[0]);
        $this->assertInstanceOf(FileResource::class, $container->getResources()[1]);
        $this->assertSame(
            \dirname(__DIR__, 2) . '/src/Resources/config/services.yml',
            $container->getResources()[1]->getResource()
        );
    }
}
