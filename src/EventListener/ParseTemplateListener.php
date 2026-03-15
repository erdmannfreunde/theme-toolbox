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
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\FrontendTemplate;
use Contao\StringUtil;
use Contao\Template;
use Contao\Widget;

class ParseTemplateListener
{
    #[AsHook('parseTemplate')]
    public function onParseTemplate(Template $template): void
    {
        if (!$template instanceof FrontendTemplate) {
            return;
        }

        if (InstalledVersions::isInstalled('contao/faq-bundle')) {
            if ('faqreader' === $template->type && \is_array($template->faq) && $template->faq['toolbox_classes']) {
                $template->toolbox_classes = $template->faq['toolbox_classes'];
            }
        }

        if (!$template->toolbox_classes) {
            return;
        }

        $template->class .= ' '.$this->uniqueClasses($template->toolbox_classes);
    }

    #[AsHook('getContentElement')]
    public function onGetContentElement(mixed $element, string $buffer): string
    {
        if (!\is_object($element) || 'alias' !== ($element->type ?? null) || !($element->toolbox_classes ?? null)) {
            return $buffer;
        }

        return preg_replace(
            '/class="(.+?)"/',
            \sprintf('class="$1 %s"', $this->uniqueClasses((string) $element->toolbox_classes)),
            $buffer,
            1,
        ) ?? $buffer;
    }

    #[AsHook('parseWidget')]
    public function onParseWidget(string $buffer, Widget $widget): string
    {
        if (!$widget->toolbox_classes) {
            return $buffer;
        }

        return preg_replace(
            '/class="(.+?)"/',
            \sprintf('class="$1 %s"', $this->uniqueClasses($widget->toolbox_classes)),
            $buffer,
            1,
        );
    }

    private function uniqueClasses(string $classes): string
    {
        return implode(' ', array_unique(StringUtil::trimsplit(' ', $classes)));
    }
}
