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

class ThemeJsFileManager
{
    private const JS_DIR = 'js';

    public function __construct(
        private readonly string $projectDir,
        private readonly Filesystem $filesystem,
        private readonly string $layoutDir = 'layout',
        private readonly string $customDir = 'layout/custom',
    ) {
    }

    private function validateRelativePath(string $relativePath): void
    {
        if (
            '' === $relativePath
            || str_contains($relativePath, '..')
            || str_starts_with($relativePath, '/')
            || str_contains($relativePath, "\0")
            || str_contains($relativePath, '\\')
        ) {
            throw new \InvalidArgumentException('Invalid file path.');
        }
    }

    private function validateThemeName(string $themeName): void
    {
        if (
            '' === $themeName
            || str_contains($themeName, '..')
            || str_contains($themeName, '/')
            || str_contains($themeName, '\\')
            || str_contains($themeName, "\0")
        ) {
            throw new \InvalidArgumentException('Invalid theme name.');
        }
    }

    /**
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
            $themes[$dir->getFilename()] = $dir->getRealPath();
        }

        return $themes;
    }

    public function getThemePath(string $themeName): ?string
    {
        return $this->getAvailableThemes()[$themeName] ?? null;
    }

    /**
     * @return array<int, array{path: string, name: string, directory: string, isCustom: bool, hasCustom: bool, isCustomOnly: bool}>
     */
    public function getJsFiles(string $themeName): array
    {
        $this->validateThemeName($themeName);

        $files = [];
        $seen = [];

        $themePath = $this->getThemePath($themeName);

        if ($themePath) {
            $jsPath = $themePath . '/' . self::JS_DIR;

            if (is_dir($jsPath)) {
                $finder = new Finder();
                $finder->files()->in($jsPath)->name('*.js')->sortByName();

                foreach ($finder as $file) {
                    $rel = $file->getRelativePathname();
                    $seen[$rel] = true;
                    $customPath = $this->getCustomFilePath($rel);

                    $files[] = [
                        'path' => $rel,
                        'name' => $file->getFilename(),
                        'directory' => $file->getRelativePath(),
                        'isCustom' => false,
                        'hasCustom' => $this->filesystem->exists($customPath),
                        'isCustomOnly' => false,
                    ];
                }
            }
        }

        $customJsPath = $this->getCustomDirPath();

        if (is_dir($customJsPath)) {
            $finder = new Finder();
            $finder->files()->in($customJsPath)->name('*.js')->sortByName();

            foreach ($finder as $file) {
                $rel = $file->getRelativePathname();

                if (isset($seen[$rel])) {
                    continue;
                }

                $files[] = [
                    'path' => $rel,
                    'name' => $file->getFilename(),
                    'directory' => $file->getRelativePath(),
                    'isCustom' => true,
                    'hasCustom' => true,
                    'isCustomOnly' => true,
                ];
            }
        }

        usort($files, fn ($a, $b) => strcmp($a['path'], $b['path']));

        return $files;
    }

    public function getFileContent(string $themeName, string $relativePath, bool $preferCustom = true): ?string
    {
        $this->validateThemeName($themeName);
        $this->validateRelativePath($relativePath);

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

    public function getOriginalFileContent(string $themeName, string $relativePath): ?string
    {
        $this->validateThemeName($themeName);
        $this->validateRelativePath($relativePath);

        $originalPath = $this->getOriginalFilePath($themeName, $relativePath);

        if ($this->filesystem->exists($originalPath)) {
            return file_get_contents($originalPath);
        }

        return null;
    }

    public function saveCustomFile(string $relativePath, string $content): bool
    {
        $this->validateRelativePath($relativePath);

        $customPath = $this->getCustomFilePath($relativePath);
        $customDir = \dirname($customPath);

        if (!is_dir($customDir)) {
            $this->filesystem->mkdir($customDir, 0755);
        }

        return file_put_contents($customPath, $content) !== false;
    }

    public function deleteCustomFile(string $relativePath): bool
    {
        $this->validateRelativePath($relativePath);

        $customPath = $this->getCustomFilePath($relativePath);

        if ($this->filesystem->exists($customPath)) {
            $this->filesystem->remove($customPath);

            return true;
        }

        return false;
    }

    public function renameCustomFile(string $oldPath, string $newPath): bool
    {
        $this->validateRelativePath($oldPath);
        $this->validateRelativePath($newPath);

        $oldCustomPath = $this->getCustomFilePath($oldPath);
        $newCustomPath = $this->getCustomFilePath($newPath);

        if (!$this->filesystem->exists($oldCustomPath)) {
            return false;
        }

        $targetDir = \dirname($newCustomPath);

        if (!is_dir($targetDir)) {
            $this->filesystem->mkdir($targetDir, 0755);
        }

        $this->filesystem->rename($oldCustomPath, $newCustomPath);

        return true;
    }

    public function hasCustomFile(string $relativePath): bool
    {
        $this->validateRelativePath($relativePath);

        return $this->filesystem->exists($this->getCustomFilePath($relativePath));
    }

    public function getOriginalFilePath(string $themeName, string $relativePath): string
    {
        return $this->projectDir . '/' . $this->layoutDir . '/' . $themeName . '/' . self::JS_DIR . '/' . $relativePath;
    }

    public function getCustomFilePath(string $relativePath): string
    {
        return $this->projectDir . '/' . $this->customDir . '/' . self::JS_DIR . '/' . $relativePath;
    }

    public function getCustomDirPath(): string
    {
        return $this->projectDir . '/' . $this->customDir . '/' . self::JS_DIR;
    }

    /**
     * @return list<string>
     */
    public function getJsDirectories(string $themeName): array
    {
        $this->validateThemeName($themeName);

        $dirs = [];

        $themePath = $this->getThemePath($themeName);
        $themeJsPath = $themePath ? $themePath . '/' . self::JS_DIR : null;

        foreach ([$themeJsPath, $this->getCustomDirPath()] as $base) {
            if (!$base || !is_dir($base)) {
                continue;
            }

            $finder = new Finder();
            $finder->directories()->in($base);

            foreach ($finder as $dir) {
                $dirs[$dir->getRelativePathname()] = true;
            }
        }

        $result = array_keys($dirs);
        sort($result);

        return $result;
    }
}
