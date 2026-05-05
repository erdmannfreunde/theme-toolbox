<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\Service;

use Symfony\Component\Finder\Finder;

class ThemeJsFileManager extends ThemeFileManager
{
    /**
     * @return array<int, array{path: string, name: string, directory: string, isCustom: bool, hasCustom: bool, isCustomOnly: bool}>
     */
    public function getJsFiles(string $themeName): array
    {
        /** @var array<int, array{path: string, name: string, directory: string, isCustom: bool, hasCustom: bool, isCustomOnly: bool}> $entries */
        return $this->buildFileEntries($themeName);
    }

    /**
     * @return list<string>
     */
    public function getJsDirectories(string $themeName): array
    {
        return $this->getDirectories($themeName);
    }

    protected function getAssetSubDir(): string
    {
        return 'js';
    }

    protected function configureFileFinder(Finder $finder): void
    {
        $finder->name('*.js');
    }
}
