<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener;

use Contao\Automator;
use Contao\Config;
use Contao\CoreBundle\ServiceAnnotation\Hook;

/**
 * Purge the script (css) cache each time.
 *
 * @Hook("replaceDynamicScriptTags")
 */
class DisableCssCachingListener
{
    public function __invoke($buffer): string
    {
        // Do not bypass in debug mode, in debug mode the css files are generated on the fly nonetheless.
        if (
            isset($GLOBALS['TL_USER_CSS'])
            && !empty($GLOBALS['TL_USER_CSS']) && \is_array($GLOBALS['TL_USER_CSS'])
            && Config::get('bypassScriptCache')
            && !Config::get('debugMode')
        ) {
            // Purging script cache is the only way to be compatible with Contao versions 4.4 to 4.6
            $automator = new Automator();
            $automator->purgeScriptCache();

            $time = time();

            foreach (array_unique($GLOBALS['TL_USER_CSS']) as $i => $stylesheet) {
                // Add version (mtime) flag. Ignore if one is already present, last flag wins.
                $GLOBALS['TL_USER_CSS'][$i] = $stylesheet.'|'.$time;
            }
        }

        return $buffer;
    }
}
