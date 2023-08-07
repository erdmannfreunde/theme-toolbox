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

use Composer\InstalledVersions;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\FrontendTemplate;
use Contao\StringUtil;
use Contao\Template;
use Contao\Widget;

class ParseTemplateListener
{
    /**
     * @Hook("parseTemplate")
     */
    public function onParseTemplate(Template $template): void
    {
        if (!$template instanceof FrontendTemplate) {
            return;
        }

        if (InstalledVersions::isInstalled('contao/faq-bundle')) {
            if ($template->type === 'faqreader' && is_array($template->faq) && $template->faq['toolbox_classes']) {
                $template->toolbox_classes = $template->faq['toolbox_classes'];
            }
        }

        if (!$template->toolbox_classes) {
            return;
        }

        $template->class .= ' '.$this->uniqueClasses($template->toolbox_classes);
    }

    /**
     * @Hook("parseWidget")
     */
    public function onParseWidget(string $buffer, Widget $widget): string
    {
        if (!$widget->toolbox_classes) {
            return $buffer;
        }

        return preg_replace(
            '/class="(.+?)"/',
            sprintf('class="$1 %s"', $this->uniqueClasses($widget->toolbox_classes)),
            $buffer,
            1
        );
    }

    private function uniqueClasses(string $classes): string
    {
        return implode(' ', array_unique(StringUtil::trimsplit(' ', $classes)));
    }
}
