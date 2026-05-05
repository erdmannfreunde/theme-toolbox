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
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ThemeImageFileManager
{
    private const IMG_DIR = 'img';

    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'avif'];

    public const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

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
     * Validate a file name (no slashes).
     */
    private function validateFileName(string $fileName): void
    {
        if (
            '' === $fileName
            || str_contains($fileName, '..')
            || str_contains($fileName, '/')
            || str_contains($fileName, '\\')
            || str_contains($fileName, "\0")
        ) {
            throw new \InvalidArgumentException('Invalid file name.');
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

    public function getOriginalFilePath(string $themeName, string $relativePath): string
    {
        return $this->projectDir . '/' . $this->layoutDir . '/' . $themeName . '/' . self::IMG_DIR . '/' . $relativePath;
    }

    public function getCustomFilePath(string $relativePath): string
    {
        return $this->projectDir . '/' . $this->customDir . '/' . self::IMG_DIR . '/' . $relativePath;
    }

    public function getCustomDirPath(): string
    {
        return $this->projectDir . '/' . $this->customDir . '/' . self::IMG_DIR;
    }

    public function getThemeImgDirPath(string $themeName): ?string
    {
        $themePath = $this->getThemePath($themeName);

        if (!$themePath) {
            return null;
        }

        $imgPath = $themePath . '/' . self::IMG_DIR;

        return is_dir($imgPath) ? $imgPath : null;
    }

    /**
     * Get all image files + directories for a theme (merged with custom).
     *
     * @return array<int, array{path: string, name: string, directory: string, isCustom: bool, hasCustom: bool, isCustomOnly: bool, extension: string}>
     */
    public function getImageFiles(string $themeName): array
    {
        $this->validateThemeName($themeName);

        $files = [];
        $seen = [];

        $themeImgPath = $this->getThemeImgDirPath($themeName);

        if ($themeImgPath) {
            $finder = new Finder();
            $finder->files()->in($themeImgPath)->sortByName();

            foreach ($finder as $file) {
                $ext = strtolower($file->getExtension());
                if (!\in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                    continue;
                }

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
                    'extension' => $ext,
                ];
            }
        }

        $customImgPath = $this->getCustomDirPath();

        if (is_dir($customImgPath)) {
            $finder = new Finder();
            $finder->files()->in($customImgPath)->sortByName();

            foreach ($finder as $file) {
                $ext = strtolower($file->getExtension());
                if (!\in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                    continue;
                }

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
                    'extension' => $ext,
                ];
            }
        }

        usort($files, fn ($a, $b) => strcmp($a['path'], $b['path']));

        return $files;
    }

    /**
     * Get all directories (from theme + custom) for folder tree rendering.
     *
     * @return list<string>
     */
    public function getImageDirectories(string $themeName): array
    {
        $this->validateThemeName($themeName);

        $dirs = [];

        foreach ([$this->getThemeImgDirPath($themeName), $this->getCustomDirPath()] as $base) {
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

    public function hasCustomFile(string $relativePath): bool
    {
        $this->validateRelativePath($relativePath);

        return $this->filesystem->exists($this->getCustomFilePath($relativePath));
    }

    public function hasOriginalFile(string $themeName, string $relativePath): bool
    {
        $this->validateThemeName($themeName);
        $this->validateRelativePath($relativePath);

        return $this->filesystem->exists($this->getOriginalFilePath($themeName, $relativePath));
    }

    /**
     * Get absolute path to serve a file (prefers custom).
     */
    public function getServablePath(string $themeName, string $relativePath): ?string
    {
        $this->validateThemeName($themeName);
        $this->validateRelativePath($relativePath);

        $customPath = $this->getCustomFilePath($relativePath);

        if ($this->filesystem->exists($customPath) && is_file($customPath)) {
            return $customPath;
        }

        $originalPath = $this->getOriginalFilePath($themeName, $relativePath);

        if ($this->filesystem->exists($originalPath) && is_file($originalPath)) {
            return $originalPath;
        }

        return null;
    }

    public function getOriginalServablePath(string $themeName, string $relativePath): ?string
    {
        $this->validateThemeName($themeName);
        $this->validateRelativePath($relativePath);

        $originalPath = $this->getOriginalFilePath($themeName, $relativePath);

        if ($this->filesystem->exists($originalPath) && is_file($originalPath)) {
            return $originalPath;
        }

        return null;
    }

    /**
     * Save an uploaded image to the custom directory at the given relative path.
     */
    public function saveUploadedImage(string $relativePath, UploadedFile $file): void
    {
        $this->validateRelativePath($relativePath);

        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($relativePath, \PATHINFO_EXTENSION));

        if (!\in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported image extension: %s', $ext ?: '(none)'));
        }

        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            throw new \InvalidArgumentException('File exceeds maximum upload size of 5 MB.');
        }

        $customPath = $this->getCustomFilePath($relativePath);
        $customDir = \dirname($customPath);

        if (!is_dir($customDir)) {
            $this->filesystem->mkdir($customDir, 0755);
        }

        $file->move($customDir, basename($customPath));
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

        $ext = strtolower(pathinfo($newPath, \PATHINFO_EXTENSION));
        if (!\in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Invalid target file extension.');
        }

        if ($this->filesystem->exists($newCustomPath)) {
            throw new \InvalidArgumentException('Target file already exists.');
        }

        $targetDir = \dirname($newCustomPath);

        if (!is_dir($targetDir)) {
            $this->filesystem->mkdir($targetDir, 0755);
        }

        $this->filesystem->rename($oldCustomPath, $newCustomPath);

        return true;
    }

    /**
     * Create a folder inside the custom image directory.
     */
    public function createCustomFolder(string $relativePath): bool
    {
        $this->validateRelativePath($relativePath);

        $customPath = $this->getCustomFilePath($relativePath);

        if (is_dir($customPath)) {
            throw new \InvalidArgumentException('Folder already exists.');
        }

        if (is_file($customPath)) {
            throw new \InvalidArgumentException('A file with this name already exists.');
        }

        $this->filesystem->mkdir($customPath, 0755);

        return true;
    }
}
