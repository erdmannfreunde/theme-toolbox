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

use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ThemeImageFileManager extends ThemeFileManager
{
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'avif'];

    public const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

    public function getThemeImgDirPath(string $themeName): string|null
    {
        return $this->getThemeAssetDirPath($themeName);
    }

    /**
     * @return array<int, array{path: string, name: string, directory: string, isCustom: bool, hasCustom: bool, isCustomOnly: bool, extension: string}>
     */
    public function getImageFiles(string $themeName): array
    {
        /** @var array<int, array{path: string, name: string, directory: string, isCustom: bool, hasCustom: bool, isCustomOnly: bool, extension: string}> $entries */
        return $this->buildFileEntries($themeName);
    }

    /**
     * @return list<string>
     */
    public function getImageDirectories(string $themeName): array
    {
        return $this->getDirectories($themeName);
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
    public function getServablePath(string $themeName, string $relativePath): string|null
    {
        $this->validateThemeName($themeName);
        $this->validateRelativePath($relativePath);

        $customPath = $this->getCustomFilePath($relativePath);

        if ($this->filesystem->exists($customPath) && is_file($customPath)) {
            return $customPath;
        }

        return $this->getOriginalServablePath($themeName, $relativePath);
    }

    public function getOriginalServablePath(string $themeName, string $relativePath): string|null
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

        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($relativePath, PATHINFO_EXTENSION));

        if (!\in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported image extension: %s', $ext ?: '(none)'));
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

    public function renameCustomFile(string $oldPath, string $newPath): bool
    {
        $this->validateRelativePath($oldPath);
        $this->validateRelativePath($newPath);

        $ext = strtolower(pathinfo($newPath, PATHINFO_EXTENSION));
        if (!\in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Invalid target file extension.');
        }

        if ($this->filesystem->exists($this->getCustomFilePath($newPath))) {
            throw new \InvalidArgumentException('Target file already exists.');
        }

        return parent::renameCustomFile($oldPath, $newPath);
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

    protected function getAssetSubDir(): string
    {
        return 'img';
    }

    protected function acceptFile(SplFileInfo $file): bool
    {
        return \in_array(strtolower($file->getExtension()), self::ALLOWED_EXTENSIONS, true);
    }

    protected function buildEntry(SplFileInfo $file, bool $isCustom, bool $hasCustom, bool $isCustomOnly): array
    {
        $entry = parent::buildEntry($file, $isCustom, $hasCustom, $isCustomOnly);
        $entry['extension'] = strtolower($file->getExtension());

        return $entry;
    }
}
