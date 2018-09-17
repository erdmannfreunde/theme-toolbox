<?php

/**
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) 2018-2018 Erdmann & Freunde.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    erdmannfreunde/theme-toolbox
 * @author     Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright  2018-2018 Erdmann & Freunde.
 * @license    https://github.com/erdmannfreunde/theme-toolbox/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

namespace ErdmannFreunde\ThemeToolboxBundle\Test;

use ErdmannFreunde\ThemeToolboxBundle\DependencyInjection\ErdmannFreundeThemeToolboxExtension;
use ErdmannFreunde\ThemeToolboxBundle\ErdmannFreundeThemeToolboxBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\ComposerResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ContaoBlackForestContaoSkeletonBundleTest
 *
 * @covers \ErdmannFreunde\ThemeToolboxBundle\ContaoBlackForestContaoSkeletonBundle
 */
class ErdmannFreundeThemeUtilsBundleTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        $bundle = new ErdmannFreundeThemeToolboxBundle();

        $this->assertInstanceOf(ErdmannFreundeThemeToolboxBundle::class, $bundle);
    }

    public function testReturnsTheContainerExtension()
    {
        $extension = (new ErdmannFreundeThemeToolboxBundle())->getContainerExtension();

        $this->assertInstanceOf(ErdmannFreundeThemeToolboxExtension::class, $extension);
    }

    /**
     * @covers \ErdmannFreunde\ThemeToolboxBundle\DependencyInjection\ErdmannFreundeThemeToolboxExtension::load
     */
    public function testLoadExtensionConfiguration()
    {
        $extension = (new ErdmannFreundeThemeToolboxBundle())->getContainerExtension();
        $container = new ContainerBuilder();

        $extension->load([], $container);

        $this->assertInstanceOf(ComposerResource::class, $container->getResources()[0]);
        $this->assertInstanceOf(FileResource::class, $container->getResources()[1]);
        $this->assertSame(
            \dirname(__DIR__) . '/src/Resources/config/services.yml',
            $container->getResources()[1]->getResource()
        );
    }
}
