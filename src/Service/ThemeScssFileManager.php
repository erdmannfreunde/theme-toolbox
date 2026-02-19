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

    /** @var array<string, array<int, array{path: string, name: string, directory: string, isCustom: bool, hasCustom: bool, isCustomOnly: bool}>> */
    private array $scssFilesCache = [];

    public function __construct(
        private readonly string $projectDir,
        private readonly Filesystem $filesystem,
        private readonly string $layoutDir = 'layout',
        private readonly string $customDir = 'layout/custom',
    ) {
    }

    /**
     * Validate a relative path to prevent directory traversal attacks.
     *
     * @throws \InvalidArgumentException if the path is invalid
     */
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

    /**
     * Validate a theme name to prevent directory traversal attacks.
     *
     * @throws \InvalidArgumentException if the theme name is invalid
     */
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
        if (isset($this->scssFilesCache[$themeName])) {
            return $this->scssFilesCache[$themeName];
        }

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

        return $this->scssFilesCache[$themeName] = $files;
    }

    /**
     * Get the content of an SCSS file.
     */
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

    /**
     * Get the original file content.
     */
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

    /**
     * Save file content to custom directory.
     */
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

    /**
     * Delete a custom file (revert to original).
     */
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

    /**
     * Rename a custom file.
     */
    public function renameCustomFile(string $oldPath, string $newPath): bool
    {
        $this->validateRelativePath($oldPath);
        $this->validateRelativePath($newPath);

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
        $this->validateRelativePath($relativePath);

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
     * Get asset directories (fonts, img, js) for a theme, including custom overrides.
     *
     * @return array<string, list<string>> Map of asset type to source directories (theme first, custom second)
     */
    public function getThemeAssetDirs(string $themeName): array
    {
        $dirs = [];

        foreach (['fonts', 'img', 'js'] as $type) {
            $sources = [];

            // Theme directory first
            $themePath = $this->getThemePath($themeName);

            if ($themePath) {
                $dir = $themePath . '/' . $type;

                if (is_dir($dir)) {
                    $sources[] = $dir;
                }
            }

            // Custom directory second (overrides theme files)
            $customDir = $this->projectDir . '/' . $this->customDir . '/' . $type;

            if (is_dir($customDir)) {
                $sources[] = $customDir;
            }

            if ($sources) {
                $dirs[$type] = $sources;
            }
        }

        return $dirs;
    }

    /**
     * Get all non-partial (entry point) SCSS files for a theme.
     * Returns files without leading underscore at depth 0 from both theme and custom directory.
     *
     * @return array<string, string>
     */
    public function getEntryPointFiles(string $themeName): array
    {
        $files = [];

        // From theme directory (depth 0 only)
        $themePath = $this->getThemePath($themeName);

        if ($themePath) {
            $scssPath = $themePath . '/' . self::SCSS_DIR;

            if (is_dir($scssPath)) {
                $finder = new Finder();
                $finder->files()->in($scssPath)->name('*.scss')->depth(0)->sortByName();

                foreach ($finder as $file) {
                    if (!str_starts_with($file->getFilename(), '_') && $file->getFilename() !== 'tinymce.scss') {
                        $name = $file->getFilenameWithoutExtension();
                        $files[$name] = $name;
                    }
                }
            }
        }

        // From custom directory (depth 0 only) — adds new files and overrides theme files with same name
        $customScssPath = $this->getCustomDirPath();

        if (is_dir($customScssPath)) {
            $finder = new Finder();
            $finder->files()->in($customScssPath)->name('*.scss')->depth(0)->sortByName();

            foreach ($finder as $file) {
                if (!str_starts_with($file->getFilename(), '_') && $file->getFilename() !== 'tinymce.scss') {
                    $name = $file->getFilenameWithoutExtension();
                    $files[$name] = $name;
                }
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * Get the path for a given entry point SCSS file (checking custom first).
     */
    public function getEntryPointPath(string $themeName, string $fileName): ?string
    {
        if (!str_ends_with($fileName, '.scss')) {
            $fileName .= '.scss';
        }

        $customPath = $this->getCustomFilePath($fileName);

        if ($this->filesystem->exists($customPath)) {
            return $customPath;
        }

        $originalPath = $this->getOriginalFilePath($themeName, $fileName);

        if ($this->filesystem->exists($originalPath)) {
            return $originalPath;
        }

        return null;
    }

    /**
     * Get the default.scss path for a theme (checking custom first).
     */
    public function getDefaultScssPath(string $themeName): ?string
    {
        return $this->getEntryPointPath($themeName, 'default.scss');
    }
}
