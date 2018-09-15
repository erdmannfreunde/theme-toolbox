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

namespace ErdmannFreunde\ThemeUtilsBundle\Test\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use ErdmannFreunde\ThemeUtilsBundle\ContaoManager\Plugin;
use ErdmannFreunde\ThemeUtilsBundle\ErdmannFreundeThemeUtilsBundle;
use PHPUnit\Framework\TestCase;

/**
 * Class PluginTest
 */
class PluginTest extends TestCase
{
    /**
     * Test get bundles.
     *
     * @cover Plugin::getBundles
     */
    public function testGetBundles()
    {
        $plugin = new Plugin();
        $parser = $this->getMockBuilder(ParserInterface::class)->getMock();

        $bundleConfig1 = BundleConfig::create(ErdmannFreundeThemeUtilsBundle::class)
            ->setLoadAfter(
                [
                    ContaoCoreBundle::class
                ]
            );

        $this->assertArraySubset($plugin->getBundles($parser), [$bundleConfig1]);
    }
}
