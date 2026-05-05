<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener\DataContainer;

use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;

class LayoutThemeScssOptionsCallback
{
    public function __construct(
        private readonly ThemeScssFileManager $fileManager,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function __invoke(): array
    {
        $themes = $this->fileManager->getAvailableThemes();

        if (empty($themes)) {
            return [];
        }

        $themeName = array_key_first($themes);

        $entries = $this->fileManager->getEntryPointFiles($themeName);
        unset($entries['tinymce']);

        return $entries;
    }
}
