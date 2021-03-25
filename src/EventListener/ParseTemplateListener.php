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

use Contao\Template;

class ParseTemplateListener
{

    public function onParseTemplate(Template $template): void
    {
        if (!$template instanceof FrontendTemplate) {
            return;
        }

        if (!$template->toolbox_classes) {
            return;
        }

        $template->class .= ' ' . $template->toolbox_classes;
    }
}
