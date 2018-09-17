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

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener;

use Contao\Automator;
use Contao\Config;

/**
 * Class DisableCssCachingListener
 *
 * @package ErdmannFreunde\ThemeToolboxBundle\EventListener
 */
class DisableCssCachingListener
{
    /**
     * Purge the script (css) cache each time.
     *
     * @param string $buffer The string with the tags to be replaced.
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public function onReplaceDynamicScriptTags(string $buffer): string
    {
        // Do not bypass in debug mode, in debug mode the css files are generated on the fly nonetheless.
        if (\is_array($GLOBALS['TL_USER_CSS'])
            && !empty($GLOBALS['TL_USER_CSS'])
            && Config::get('bypassScriptCache')
            && !Config::get('debugMode')) {
            // Purging script cache is the only way to be compatible with Contao versions 4.4 to 4.6
            $automator = new Automator();
            $automator->purgeScriptCache();
        }

        return $buffer;
    }
}
