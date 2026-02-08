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

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class ThemeScssFileManager
{
    private const SCSS_DIR = 'scss';

    public function __construct(
        private readonly string $projectDir,
        private readonly Filesystem $filesystem,
        private readonly string $layoutDir = 'layout',
        private readonly string $customDir = 'layout/custom',
    ) {
    }

    /**
     * Get all available themes (directories in layout/).
     *
     * @return array<string, string>
     */
    public function getAvailableThemes(): array
    {
        $layoutPath = $this->projectDir . '/' . $this->layoutDir;

        if (!is_dir($layoutPath)) {
            return [];
        }

        $themes = [];
        $finder = new Finder();
        $finder->directories()->in($layoutPath)->depth(0)->notName('custom');

        foreach ($finder as $dir) {
            $themeName = $dir->getFilename();
            $scssPath = $dir->getRealPath() . '/' . self::SCSS_DIR;

            if (is_dir($scssPath)) {
                $themes[$themeName] = $dir->getRealPath();
            }
        }

        return $themes;
    }

    /**
     * Get all SCSS files for a theme.
     *
     * @return array<int, array{path: string, name: string, isCustom: bool, hasCustom: bool, isCustomOnly: bool}>
     */
    public function getScssFiles(string $themeName): array
    {
        $files = [];
        $seenPaths = [];

        // First, get files from the theme directory
        $themePath = $this->getThemePath($themeName);

        if ($themePath) {
            $scssPath = $themePath . '/' . self::SCSS_DIR;

            if (is_dir($scssPath)) {
                $finder = new Finder();
                $finder->files()->in($scssPath)->name('*.scss')->sortByName();

                foreach ($finder as $file) {
                    $relativePath = $file->getRelativePathname();
                    $customPath = $this->getCustomFilePath($relativePath);
                    $seenPaths[$relativePath] = true;

                    $files[] = [
                        'path' => $relativePath,
                        'name' => $file->getFilename(),
                        'directory' => $file->getRelativePath(),
                        'isCustom' => false,
                        'hasCustom' => $this->filesystem->exists($customPath),
                        'isCustomOnly' => false,
                    ];
                }
            }
        }

        // Then, add custom-only files (files that exist only in layout/custom/scss/)
        $customScssPath = $this->getCustomDirPath();

        if (is_dir($customScssPath)) {
            $customFinder = new Finder();
            $customFinder->files()->in($customScssPath)->name('*.scss')->sortByName();

            foreach ($customFinder as $file) {
                $relativePath = $file->getRelativePathname();

                // Skip if we already have this file from the theme directory
                if (isset($seenPaths[$relativePath])) {
                    continue;
                }

                $files[] = [
                    'path' => $relativePath,
                    'name' => $file->getFilename(),
                    'directory' => $file->getRelativePath(),
                    'isCustom' => true,
                    'hasCustom' => true,
                    'isCustomOnly' => true,
                ];
            }
        }

        // Sort all files by path
        usort($files, fn ($a, $b) => strcmp($a['path'], $b['path']));

        return $files;
    }

    /**
     * Get the content of an SCSS file.
     */
    public function getFileContent(string $themeName, string $relativePath, bool $preferCustom = true): ?string
    {
        $customPath = $this->getCustomFilePath($relativePath);
        $originalPath = $this->getOriginalFilePath($themeName, $relativePath);

        if ($preferCustom && $this->filesystem->exists($customPath)) {
            return file_get_contents($customPath);
        }

        if ($this->filesystem->exists($originalPath)) {
            return file_get_contents($originalPath);
        }

        return null;
    }

    /**
     * Get the original file content.
     */
    public function getOriginalFileContent(string $themeName, string $relativePath): ?string
    {
        $originalPath = $this->getOriginalFilePath($themeName, $relativePath);

        if ($this->filesystem->exists($originalPath)) {
            return file_get_contents($originalPath);
        }

        return null;
    }

    /**
     * Save file content to custom directory.
     */
    public function saveCustomFile(string $relativePath, string $content): bool
    {
        $customPath = $this->getCustomFilePath($relativePath);
        $customDir = \dirname($customPath);

        if (!is_dir($customDir)) {
            $this->filesystem->mkdir($customDir, 0755);
        }

        return (bool) file_put_contents($customPath, $content);
    }

    /**
     * Delete a custom file (revert to original).
     */
    public function deleteCustomFile(string $relativePath): bool
    {
        $customPath = $this->getCustomFilePath($relativePath);

        if ($this->filesystem->exists($customPath)) {
            $this->filesystem->remove($customPath);

            return true;
        }

        return false;
    }

    /**
     * Rename a custom file.
     */
    public function renameCustomFile(string $oldPath, string $newPath): bool
    {
        $oldCustomPath = $this->getCustomFilePath($oldPath);
        $newCustomPath = $this->getCustomFilePath($newPath);

        if (!$this->filesystem->exists($oldCustomPath)) {
            return false;
        }

        // Ensure target directory exists
        $targetDir = \dirname($newCustomPath);

        if (!is_dir($targetDir)) {
            $this->filesystem->mkdir($targetDir, 0755);
        }

        $this->filesystem->rename($oldCustomPath, $newCustomPath);

        return true;
    }

    /**
     * Check if a custom version exists.
     */
    public function hasCustomFile(string $relativePath): bool
    {
        return $this->filesystem->exists($this->getCustomFilePath($relativePath));
    }

    /**
     * Get the path to the original file.
     */
    public function getOriginalFilePath(string $themeName, string $relativePath): string
    {
        return $this->projectDir . '/' . $this->layoutDir . '/' . $themeName . '/' . self::SCSS_DIR . '/' . $relativePath;
    }

    /**
     * Get the path to the custom file.
     */
    public function getCustomFilePath(string $relativePath): string
    {
        return $this->projectDir . '/' . $this->customDir . '/' . self::SCSS_DIR . '/' . $relativePath;
    }

    /**
     * Get the custom directory path.
     */
    public function getCustomDirPath(): string
    {
        return $this->projectDir . '/' . $this->customDir . '/' . self::SCSS_DIR;
    }

    /**
     * Get theme path by name.
     */
    public function getThemePath(string $themeName): ?string
    {
        $themes = $this->getAvailableThemes();

        return $themes[$themeName] ?? null;
    }

    /**
     * Get the default.scss path for a theme (checking custom first).
     */
    public function getDefaultScssPath(string $themeName): ?string
    {
        $customPath = $this->getCustomFilePath('default.scss');

        if ($this->filesystem->exists($customPath)) {
            return $customPath;
        }

        $originalPath = $this->getOriginalFilePath($themeName, 'default.scss');

        if ($this->filesystem->exists($originalPath)) {
            return $originalPath;
        }

        return null;
    }
}
