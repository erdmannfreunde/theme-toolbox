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
use Symfony\Component\Finder\SplFileInfo;

abstract class ThemeFileManager
{
    public function __construct(
        protected readonly string $projectDir,
        protected readonly Filesystem $filesystem,
        protected readonly string $layoutDir = 'layout',
        protected readonly string $customDir = 'layout/custom',
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableThemes(): array
    {
        $layoutPath = $this->projectDir.'/'.$this->layoutDir;

        if (!is_dir($layoutPath)) {
            return [];
        }

        $themes = [];
        $finder = new Finder();
        $finder->directories()->in($layoutPath)->depth(0)->notName('custom');

        foreach ($finder as $dir) {
            $realPath = $dir->getRealPath();

            if (false === $realPath || !$this->themeQualifies($realPath)) {
                continue;
            }

            $themes[$dir->getFilename()] = $realPath;
        }

        return $themes;
    }

    public function getThemePath(string $themeName): string|null
    {
        return $this->getAvailableThemes()[$themeName] ?? null;
    }

    public function getOriginalFilePath(string $themeName, string $relativePath): string
    {
        return $this->projectDir.'/'.$this->layoutDir.'/'.$themeName.'/'.$this->getAssetSubDir().'/'.$relativePath;
    }

    public function getCustomFilePath(string $relativePath): string
    {
        return $this->projectDir.'/'.$this->customDir.'/'.$this->getAssetSubDir().'/'.$relativePath;
    }

    public function getCustomDirPath(): string
    {
        return $this->projectDir.'/'.$this->customDir.'/'.$this->getAssetSubDir();
    }

    public function getThemeAssetDirPath(string $themeName): string|null
    {
        $themePath = $this->getThemePath($themeName);

        if (!$themePath) {
            return null;
        }

        $dir = $themePath.'/'.$this->getAssetSubDir();

        return is_dir($dir) ? $dir : null;
    }

    public function getFileContent(string $themeName, string $relativePath, bool $preferCustom = true): string|null
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

    public function getOriginalFileContent(string $themeName, string $relativePath): string|null
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

        return false !== file_put_contents($customPath, $content);
    }

    public function deleteCustomFile(string $relativePath): bool
    {
        $this->validateRelativePath($relativePath);

        $customPath = $this->getCustomFilePath($relativePath);

        if ($this->filesystem->exists($customPath) && is_file($customPath)) {
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

    /**
     * @return list<string>
     */
    public function getDirectories(string $themeName): array
    {
        $this->validateThemeName($themeName);

        $dirs = [];

        foreach ([$this->getThemeAssetDirPath($themeName), $this->getCustomDirPath()] as $base) {
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

    /**
     * Asset subdirectory inside a theme (e.g. 'scss', 'js', 'img').
     */
    abstract protected function getAssetSubDir(): string;

    /**
     * Decide whether a directory under layoutDir qualifies as a theme. Default: every
     * directory qualifies. Override for stricter checks.
     */
    protected function themeQualifies(string $themePath): bool
    {
        return true;
    }

    /**
     * Constrain the Finder when listing files (e.g. ->name('*.scss')).
     */
    protected function configureFileFinder(Finder $finder): void
    {
        // no-op by default
    }

    /**
     * Per-file accept hook applied after Finder yields a file.
     */
    protected function acceptFile(SplFileInfo $file): bool
    {
        return true;
    }

    /**
     * Build the entry array for a single file. Override to add extra fields.
     *
     * @return array<string, mixed>
     */
    protected function buildEntry(SplFileInfo $file, bool $isCustom, bool $hasCustom, bool $isCustomOnly): array
    {
        return [
            'path' => $file->getRelativePathname(),
            'name' => $file->getFilename(),
            'directory' => $file->getRelativePath(),
            'isCustom' => $isCustom,
            'hasCustom' => $hasCustom,
            'isCustomOnly' => $isCustomOnly,
        ];
    }

    /**
     * @throws \InvalidArgumentException if the path is invalid
     */
    protected function validateRelativePath(string $relativePath): void
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

    /**
     * @throws \InvalidArgumentException if the theme name is invalid
     */
    protected function validateThemeName(string $themeName): void
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
     * Walk theme + custom directories and build merged file entries.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildFileEntries(string $themeName): array
    {
        $this->validateThemeName($themeName);

        $files = [];
        $seen = [];

        $themeAssetPath = $this->getThemeAssetDirPath($themeName);

        if ($themeAssetPath) {
            $finder = new Finder();
            $finder->files()->in($themeAssetPath)->sortByName();
            $this->configureFileFinder($finder);

            foreach ($finder as $file) {
                if (!$this->acceptFile($file)) {
                    continue;
                }

                $rel = $file->getRelativePathname();
                $seen[$rel] = true;
                $hasCustom = $this->filesystem->exists($this->getCustomFilePath($rel));

                $files[] = $this->buildEntry($file, false, $hasCustom, false);
            }
        }

        $customPath = $this->getCustomDirPath();

        if (is_dir($customPath)) {
            $finder = new Finder();
            $finder->files()->in($customPath)->sortByName();
            $this->configureFileFinder($finder);

            foreach ($finder as $file) {
                if (!$this->acceptFile($file)) {
                    continue;
                }

                $rel = $file->getRelativePathname();

                if (isset($seen[$rel])) {
                    continue;
                }

                $files[] = $this->buildEntry($file, true, true, true);
            }
        }

        usort($files, static fn ($a, $b) => strcmp((string) $a['path'], (string) $b['path']));

        return $files;
    }
}
