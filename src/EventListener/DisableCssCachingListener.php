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

namespace ErdmannFreunde\ThemeUtilsBundle\EventListener;

/**
 * Class DisableCssCachingListener
 *
 * @package ErdmannFreunde\ThemeUtilsBundle\EventListener
 */
class DisableCssCachingListener
{
    /**
     * Manipulate the version (mtime) flag of the external css files. This will invalidate the css caches each time.
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
        if (!empty($GLOBALS['TL_USER_CSS']) && \is_array($GLOBALS['TL_USER_CSS'])) {
            $time = time();
            foreach (array_unique($GLOBALS['TL_USER_CSS']) as $i => $stylesheet) {
                // Add version (mtime) flag. Ignore if one is already present, last flag wins.
                $GLOBALS['TL_USER_CSS'][$i] = $stylesheet . '|' . $time;
            }
        }

        return $buffer;
    }
}
