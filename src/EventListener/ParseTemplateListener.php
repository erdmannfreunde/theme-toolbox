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
        if (!\is_object($element) || !($element->toolbox_classes ?? null)) {
            return $buffer;
        }

        if (!\in_array($element->type ?? null, ['alias', 'module'], true)) {
            return $buffer;
        }

        $classes = $this->uniqueClasses((string) $element->toolbox_classes);

        return preg_replace(
            '/class="(.+?)"/',
            \sprintf('class="$1 %s"', $classes),
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

        $classes = $this->uniqueClasses($widget->toolbox_classes);

        if ($classes === '') {
            return $buffer;
        }

        // First try to append to an existing class attribute (including class="").
        $updated = preg_replace_callback(
            '/class="([^"]*)"/',
            static function (array $matches) use ($classes): string {
                $existing = trim($matches[1]);

                if ('' === $existing) {
                    return 'class="'.$classes.'"';
                }

                return 'class="'.$existing.' '.$classes.'"';
            },
            $buffer,
            1,
        );

        if (null !== $updated && $updated !== $buffer) {
            return $updated;
        }

        // Fallback: inject class attribute into first HTML tag.
        return preg_replace('/^<([a-zA-Z0-9:-]+)/', '<$1 class="'.$classes.'"', $buffer, 1) ?? $buffer;
    }

    private function uniqueClasses(string $classes): string
    {
        return implode(' ', array_unique(StringUtil::trimsplit(' ', $classes)));
    }
}
