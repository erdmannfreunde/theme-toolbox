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

class ThemeUpdateService
{
    public function __construct(
        private readonly string $projectDir,
        private readonly Filesystem $filesystem,
        private readonly string $layoutDir = 'layout',
    ) {
    }

    /**
     * @return array{success: bool, themeName: string, backupPath: string, stats: array{layoutCopied: int, layoutDeleted: int, filesCopied: int, templatesCopied: int}, error?: string}
     */
    public function processUpdate(UploadedFile $zipFile): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($zipFile->getPathname()) !== true) {
            return $this->errorResult('invalidZip');
        }

        // Detect theme name from root folder in ZIP
        $themeName = $this->detectThemeName($zip);

        if (null === $themeName) {
            $zip->close();

            return $this->errorResult('noThemeDetected');
        }

        // Create layout directory if it doesn't exist yet
        $themeLayoutDir = $this->projectDir . '/' . $this->layoutDir . '/' . $themeName;

        if (!is_dir($themeLayoutDir)) {
            $this->filesystem->mkdir($themeLayoutDir, 0755);
        }

        // Extract to temporary directory
        $tempDir = sys_get_temp_dir() . '/theme-update-' . uniqid();
        $zip->extractTo($tempDir);
        $zip->close();

        // Find the actual root directory (may be nested)
        $extractedRoot = $this->findExtractedRoot($tempDir, $themeName);

        if (null === $extractedRoot) {
            $this->filesystem->remove($tempDir);

            return $this->errorResult('invalidStructure', $themeName);
        }

        try {
            // Create backup
            $backupPath = $this->createBackup($themeName, $themeLayoutDir);

            $stats = [
                'layoutCopied' => 0,
                'layoutDeleted' => 0,
                'filesCopied' => 0,
                'templatesCopied' => 0,
            ];

            // Sync layout directory (mirror with delete)
            $sourceLayoutDir = $extractedRoot . '/layout/' . $themeName;

            if (is_dir($sourceLayoutDir)) {
                $stats = array_merge($stats, $this->syncLayoutDirectory($sourceLayoutDir, $themeLayoutDir));
            }

            // Copy files directory (no delete)
            $sourceFilesDir = $extractedRoot . '/files';

            if (is_dir($sourceFilesDir)) {
                $stats['filesCopied'] = $this->copyDirectory($sourceFilesDir, $this->projectDir . '/files');
            }

            // Copy templates directory (no delete, no overwrite)
            $sourceTemplatesDir = $extractedRoot . '/templates';

            if (is_dir($sourceTemplatesDir)) {
                $stats['templatesCopied'] = $this->copyDirectory($sourceTemplatesDir, $this->projectDir . '/templates', false);
            }
        } catch (\Exception $e) {
            $this->filesystem->remove($tempDir);

            return $this->errorResult('updateFailed', $themeName, $e->getMessage());
        }

        // Cleanup
        $this->filesystem->remove($tempDir);

        return [
            'success' => true,
            'themeName' => $themeName,
            'backupPath' => $backupPath,
            'stats' => $stats,
            'suggestCustomLayout' => $this->shouldSuggestCustomLayout(),
        ];
    }

    public function shouldSuggestCustomLayout(): bool
    {
        $filesThemeScssDir = $this->projectDir . '/files/theme/scss';
        $customLayoutDir = $this->projectDir . '/' . $this->layoutDir . '/custom';

        return is_dir($filesThemeScssDir) && !is_dir($customLayoutDir);
    }

    public function copyToCustomLayout(): int
    {
        $sourceDir = $this->projectDir . '/files/theme/scss';
        $targetDir = $this->projectDir . '/' . $this->layoutDir . '/custom/scss';

        return $this->copyDirectory($sourceDir, $targetDir);
    }

    /**
     * @return list<string>
     */
    public function findDuplicateFiles(string $themeName): array
    {
        $customDir = $this->projectDir . '/' . $this->layoutDir . '/custom';
        $themeDir = $this->projectDir . '/' . $this->layoutDir . '/' . $themeName;

        $duplicates = [];

        if (!is_dir($customDir) || !is_dir($themeDir)) {
            return $duplicates;
        }

        $finder = new Finder();
        $finder->files()->in($customDir);

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $themePath = $themeDir . '/' . $relativePath;

            if (file_exists($themePath) && hash_file('sha256', $file->getRealPath()) === hash_file('sha256', $themePath)) {
                $duplicates[] = $relativePath;
            }
        }

        return $duplicates;
    }

    /**
     * @return list<string>
     */
    public function removeDuplicateFiles(string $themeName): array
    {
        $duplicates = $this->findDuplicateFiles($themeName);
        $customDir = $this->projectDir . '/' . $this->layoutDir . '/custom';

        foreach ($duplicates as $relativePath) {
            $this->filesystem->remove($customDir . '/' . $relativePath);
        }

        if (is_dir($customDir)) {
            $this->removeEmptyDirectories($customDir);
        }

        return $duplicates;
    }

    private function detectThemeName(\ZipArchive $zip): ?string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $parts = explode('/', $name);

            // Look for pattern: [ThemeName]/layout/[theme-name]/
            if (\count($parts) >= 3 && $parts[1] === 'layout' && '' !== $parts[2]) {
                return $parts[2];
            }
        }

        return null;
    }

    private function findExtractedRoot(string $tempDir, string $themeName): ?string
    {
        // The root of the ZIP typically contains one folder (the theme name)
        $finder = new Finder();
        $finder->directories()->in($tempDir)->depth(0);

        foreach ($finder as $dir) {
            $layoutDir = $dir->getRealPath() . '/layout/' . $themeName;

            if (is_dir($layoutDir)) {
                return $dir->getRealPath();
            }
        }

        // Check if tempDir itself is the root
        if (is_dir($tempDir . '/layout/' . $themeName)) {
            return $tempDir;
        }

        return null;
    }

    private function createBackup(string $themeName, string $themeLayoutDir): string
    {
        $backupDir = $this->projectDir . '/var/backups/theme-updates';

        if (!is_dir($backupDir)) {
            $this->filesystem->mkdir($backupDir, 0755);
        }

        $backupPath = $backupDir . '/' . $themeName . '-' . date('Y-m-d_H-i-s') . '.zip';

        $zip = new \ZipArchive();

        if ($zip->open($backupPath, \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Could not create backup archive.');
        }

        // Backup layout directory
        if (is_dir($themeLayoutDir)) {
            $this->addDirectoryToZip($zip, $themeLayoutDir, 'layout/' . $themeName);
        }

        // Backup layout/custom directory
        $customLayoutDir = $this->projectDir . '/' . $this->layoutDir . '/custom';

        if (is_dir($customLayoutDir)) {
            $this->addDirectoryToZip($zip, $customLayoutDir, 'layout/custom');
        }

        // Backup files directory
        $filesDir = $this->projectDir . '/files';

        if (is_dir($filesDir)) {
            $this->addDirectoryToZip($zip, $filesDir, 'files');
        }

        // Backup templates directory
        $templatesDir = $this->projectDir . '/templates';

        if (is_dir($templatesDir)) {
            $this->addDirectoryToZip($zip, $templatesDir, 'templates');
        }

        $zip->close();

        // Return path relative to project directory
        return 'var/backups/theme-updates/' . basename($backupPath);
    }

    private function addDirectoryToZip(\ZipArchive $zip, string $directory, string $prefix): void
    {
        $finder = new Finder();
        $finder->files()->in($directory);

        foreach ($finder as $file) {
            $zip->addFile($file->getRealPath(), $prefix . '/' . $file->getRelativePathname());
        }
    }

    /**
     * @return array{layoutCopied: int, layoutDeleted: int}
     */
    private function syncLayoutDirectory(string $sourceDir, string $targetDir): array
    {
        $copied = 0;
        $deleted = 0;

        // Collect existing files in target
        $existingFiles = [];

        if (is_dir($targetDir)) {
            $finder = new Finder();
            $finder->files()->in($targetDir);

            foreach ($finder as $file) {
                $existingFiles[$file->getRelativePathname()] = true;
            }
        }

        // Copy all files from source to target
        $sourceFinder = new Finder();
        $sourceFinder->files()->in($sourceDir);

        foreach ($sourceFinder as $file) {
            $relativePath = $file->getRelativePathname();
            $targetPath = $targetDir . '/' . $relativePath;
            $targetFileDir = \dirname($targetPath);

            if (!is_dir($targetFileDir)) {
                $this->filesystem->mkdir($targetFileDir, 0755);
            }

            $this->filesystem->copy($file->getRealPath(), $targetPath, true);
            $copied++;

            // Remove from existing files list (remaining files will be deleted)
            unset($existingFiles[$relativePath]);
        }

        // Delete files that no longer exist in source
        foreach (array_keys($existingFiles) as $relativePath) {
            $this->filesystem->remove($targetDir . '/' . $relativePath);
            $deleted++;
        }

        // Clean up empty directories
        $this->removeEmptyDirectories($targetDir);

        return ['layoutCopied' => $copied, 'layoutDeleted' => $deleted];
    }

    private function copyDirectory(string $sourceDir, string $targetDir, bool $overwrite = true): int
    {
        $copied = 0;
        $finder = new Finder();
        $finder->files()->in($sourceDir);

        foreach ($finder as $file) {
            $targetPath = $targetDir . '/' . $file->getRelativePathname();

            if (!$overwrite && $this->filesystem->exists($targetPath)) {
                continue;
            }

            $targetFileDir = \dirname($targetPath);

            if (!is_dir($targetFileDir)) {
                $this->filesystem->mkdir($targetFileDir, 0755);
            }

            $this->filesystem->copy($file->getRealPath(), $targetPath, true);
            $copied++;
        }

        return $copied;
    }

    private function removeEmptyDirectories(string $directory): void
    {
        $finder = new Finder();
        $finder->directories()->in($directory)->sortByName()->reverseSorting();

        foreach ($finder as $dir) {
            if ((new Finder())->in($dir->getRealPath())->depth(0)->count() === 0) {
                $this->filesystem->remove($dir->getRealPath());
            }
        }
    }

    /**
     * @return array{success: false, themeName: string, backupPath: string, stats: array{layoutCopied: int, layoutDeleted: int, filesCopied: int, templatesCopied: int}, error: string}
     */
    private function errorResult(string $errorKey, string $themeName = '', string $detail = ''): array
    {
        return [
            'success' => false,
            'themeName' => $themeName,
            'backupPath' => '',
            'stats' => [
                'layoutCopied' => 0,
                'layoutDeleted' => 0,
                'filesCopied' => 0,
                'templatesCopied' => 0,
            ],
            'error' => $errorKey,
            'errorDetail' => $detail,
        ];
    }
}
