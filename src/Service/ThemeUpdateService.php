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
    /**
     * Number of backup archives to keep per theme, including the one just created.
     */
    private const BACKUP_KEEP = 3;

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

        if (true !== $zip->open($zipFile->getPathname())) {
            return $this->errorResult('invalidZip');
        }

        // Detect theme name from root folder in ZIP
        $themeName = $this->detectThemeName($zip);

        if (null === $themeName) {
            $zip->close();

            return $this->errorResult('noThemeDetected');
        }

        // Create layout directory if it doesn't exist yet. An empty or missing one means the
        // theme is being installed rather than updated, which changes how files/ is treated.
        // Empty counts as missing because a run that failed after the mkdir below would
        // otherwise turn every later attempt into an update that installs nothing.
        $themeLayoutDir = $this->projectDir.'/'.$this->layoutDir.'/'.$themeName;
        $isFirstRun = !is_dir($themeLayoutDir) || !(new Finder())->files()->in($themeLayoutDir)->hasResults();

        if (!is_dir($themeLayoutDir)) {
            $this->filesystem->mkdir($themeLayoutDir, 0755);
        }

        // Extract to temporary directory
        $tempDir = sys_get_temp_dir().'/theme-update-'.uniqid();
        $zip->extractTo($tempDir);
        $zip->close();

        // Find the actual root directory (may be nested)
        $extractedRoot = $this->findExtractedRoot($tempDir, $themeName);

        if (null === $extractedRoot) {
            $this->filesystem->remove($tempDir);

            return $this->errorResult('invalidStructure', $themeName);
        }

        try {
            // Determine which sub-directories of files/ this update writes to, so that the
            // backup covers exactly that and nothing more.
            $sourceFilesDir = $extractedRoot.'/files';
            $filesSubDirs = is_dir($sourceFilesDir) ? $this->resolveFilesSubDirs($sourceFilesDir, $isFirstRun) : [];

            // Create backup
            $backupPath = $this->createBackup($themeName, $themeLayoutDir, $filesSubDirs);

            $stats = [
                'layoutCopied' => 0,
                'layoutDeleted' => 0,
                'filesCopied' => 0,
                'templatesCopied' => 0,
            ];

            // Sync layout directory (mirror with delete)
            $sourceLayoutDir = $extractedRoot.'/layout/'.$themeName;

            if (is_dir($sourceLayoutDir)) {
                $stats = array_merge($stats, $this->syncLayoutDirectory($sourceLayoutDir, $themeLayoutDir));
            }

            // Copy files directory (no delete). Dot files are included so that the .public
            // marker ships with the folder it makes public.
            foreach ($filesSubDirs as $subDir) {
                $stats['filesCopied'] += $this->copyDirectory(
                    $sourceFilesDir.'/'.$subDir,
                    $this->projectDir.'/files/'.$subDir,
                    includeDotFiles: true,
                );
            }

            // Copy templates directory (no delete, no overwrite). SQL dumps are skipped: the
            // packages ship the demo dump here for Contao's theme import, which reads it
            // from the archive itself, so copying it in would only pile up per release.
            $sourceTemplatesDir = $extractedRoot.'/templates';

            if (is_dir($sourceTemplatesDir)) {
                $stats['templatesCopied'] = $this->copyDirectory($sourceTemplatesDir, $this->projectDir.'/templates', false, ['*.sql']);
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
        $filesThemeScssDir = $this->projectDir.'/files/theme/scss';
        $customLayoutDir = $this->projectDir.'/'.$this->layoutDir.'/custom';

        return is_dir($filesThemeScssDir) && !is_dir($customLayoutDir);
    }

    public function copyToCustomLayout(): int
    {
        $sourceDir = $this->projectDir.'/files/theme/scss';
        $targetDir = $this->projectDir.'/'.$this->layoutDir.'/custom/scss';

        return $this->copyDirectory($sourceDir, $targetDir);
    }

    /**
     * @return list<string>
     */
    public function findDuplicateFiles(string $themeName): array
    {
        $customDir = $this->projectDir.'/'.$this->layoutDir.'/custom';
        $themeDir = $this->projectDir.'/'.$this->layoutDir.'/'.$themeName;

        $duplicates = [];

        if (!is_dir($customDir) || !is_dir($themeDir)) {
            return $duplicates;
        }

        $finder = new Finder();
        $finder->files()->in($customDir);

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $themePath = $themeDir.'/'.$relativePath;

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
        $customDir = $this->projectDir.'/'.$this->layoutDir.'/custom';

        foreach ($duplicates as $relativePath) {
            $this->filesystem->remove($customDir.'/'.$relativePath);
        }

        if (is_dir($customDir)) {
            $this->removeEmptyDirectories($customDir);
        }

        return $duplicates;
    }

    private function detectThemeName(\ZipArchive $zip): string|null
    {
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            $parts = explode('/', $name);

            // Look for pattern: [ThemeName]/layout/[theme-name]/ The name goes straight into
            // file system paths, so anything that could escape the layout directory (".",
            // "..", slashes) is not a candidate.
            if (\count($parts) >= 3 && 'layout' === $parts[1] && preg_match('/^[A-Za-z0-9_-]+$/', $parts[2])) {
                return $parts[2];
            }
        }

        return null;
    }

    private function findExtractedRoot(string $tempDir, string $themeName): string|null
    {
        // The root of the ZIP typically contains one folder (the theme name)
        $finder = new Finder();
        $finder->directories()->in($tempDir)->depth(0);

        foreach ($finder as $dir) {
            $layoutDir = $dir->getRealPath().'/layout/'.$themeName;

            if (is_dir($layoutDir)) {
                return $dir->getRealPath();
            }
        }

        // Check if tempDir itself is the root
        if (is_dir($tempDir.'/layout/'.$themeName)) {
            return $tempDir;
        }

        return null;
    }

    /**
     * @param list<string> $filesSubDirs Sub-directories of files/ this update writes to
     */
    private function createBackup(string $themeName, string $themeLayoutDir, array $filesSubDirs = []): string
    {
        $backupDir = $this->projectDir.'/var/backups/theme-updates';

        if (!is_dir($backupDir)) {
            $this->filesystem->mkdir($backupDir, 0755);
        }

        $backupPath = $backupDir.'/'.$themeName.'-'.date('Y-m-d_H-i-s').'.zip';

        $zip = new \ZipArchive();

        if (true !== $zip->open($backupPath, \ZipArchive::CREATE)) {
            throw new \RuntimeException('Could not create backup archive.');
        }

        // Backup layout directory
        if (is_dir($themeLayoutDir)) {
            $this->addDirectoryToZip($zip, $themeLayoutDir, 'layout/'.$themeName);
        }

        // Backup layout/custom directory
        $customLayoutDir = $this->projectDir.'/'.$this->layoutDir.'/custom';

        if (is_dir($customLayoutDir)) {
            $this->addDirectoryToZip($zip, $customLayoutDir, 'layout/custom');
        }

        // Backup files directory, limited to the sub-directories this update writes to.
        // Everything else under files/ cannot be touched, so archiving it would only
        // copy the whole media library on every run.
        foreach ($filesSubDirs as $subDir) {
            $subDirPath = $this->projectDir.'/files/'.$subDir;

            if (is_dir($subDirPath)) {
                $this->addDirectoryToZip($zip, $subDirPath, 'files/'.$subDir, includeDotFiles: true);
            }
        }

        // Backup templates directory. SQL dumps are left out: the templates step never
        // overwrites or deletes, so an existing dump cannot be lost by an update.
        $templatesDir = $this->projectDir.'/templates';

        if (is_dir($templatesDir)) {
            $this->addDirectoryToZip($zip, $templatesDir, 'templates', ['*.sql']);
        }

        // An empty archive is never written to disk, so there is nothing to report or prune
        if (0 === $zip->count()) {
            $zip->close();

            return '';
        }

        if (!$zip->close() || !is_file($backupPath)) {
            throw new \RuntimeException('Could not write backup archive.');
        }

        $this->pruneBackups($backupDir, $themeName, $backupPath);

        // Return path relative to project directory
        return 'var/backups/theme-updates/'.basename($backupPath);
    }

    /**
     * Sub-directories of files/ the update may write to.
     *
     * An update only refreshes what is already there, so a folder somebody removed on
     * purpose (files/demo, typically) stays removed. A first run installs all of them.
     *
     * @return list<string>
     */
    private function resolveFilesSubDirs(string $sourceFilesDir, bool $isFirstRun): array
    {
        $subDirs = [];
        $finder = new Finder();
        $finder->directories()->in($sourceFilesDir)->depth(0);

        foreach ($finder as $dir) {
            $name = $dir->getFilename();

            if ($isFirstRun || is_dir($this->projectDir.'/files/'.$name)) {
                $subDirs[] = $name;
            }
        }

        return $subDirs;
    }

    /**
     * Keep the most recent backups of this theme and remove the rest.
     *
     * The archive just created is excluded explicitly rather than relying on it
     * sorting last, so a clock that moved backwards cannot make the update delete its
     * own backup.
     */
    private function pruneBackups(string $backupDir, string $themeName, string $currentBackupPath): void
    {
        $currentName = basename($currentBackupPath);

        // Match the exact name createBackup() produces. A glob like "<theme>-*.zip"
        // would also catch themes whose name starts with this one, plus hand-named
        // archives somebody dropped in here.
        $finder = new Finder();
        $finder->files()->in($backupDir)->depth(0)->name(
            '/^'.preg_quote($themeName, '/').'-\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/',
        );

        $backups = [];

        foreach ($finder as $file) {
            if ($file->getFilename() !== $currentName) {
                $backups[] = $file->getFilename();
            }
        }

        sort($backups, SORT_STRING);

        $obsolete = \array_slice($backups, 0, max(0, \count($backups) - (self::BACKUP_KEEP - 1)));

        foreach ($obsolete as $name) {
            $this->filesystem->remove($backupDir.'/'.$name);
        }
    }

    /**
     * @param list<string> $exclude File name patterns to leave out
     */
    private function addDirectoryToZip(\ZipArchive $zip, string $directory, string $prefix, array $exclude = [], bool $includeDotFiles = false): void
    {
        $finder = new Finder();
        $finder->files()->in($directory)->notName($exclude)->ignoreDotFiles(!$includeDotFiles);

        foreach ($finder as $file) {
            $zip->addFile($file->getRealPath(), $prefix.'/'.$file->getRelativePathname());
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
            $targetPath = $targetDir.'/'.$relativePath;
            $targetFileDir = \dirname($targetPath);

            if (!is_dir($targetFileDir)) {
                $this->filesystem->mkdir($targetFileDir, 0755);
            }

            $this->filesystem->copy($file->getRealPath(), $targetPath, true);
            ++$copied;

            // Remove from existing files list (remaining files will be deleted)
            unset($existingFiles[$relativePath]);
        }

        // Delete files that no longer exist in source
        foreach (array_keys($existingFiles) as $relativePath) {
            $this->filesystem->remove($targetDir.'/'.$relativePath);
            ++$deleted;
        }

        // Clean up empty directories
        $this->removeEmptyDirectories($targetDir);

        return ['layoutCopied' => $copied, 'layoutDeleted' => $deleted];
    }

    /**
     * @param list<string> $exclude File name patterns to leave out
     */
    private function copyDirectory(string $sourceDir, string $targetDir, bool $overwrite = true, array $exclude = [], bool $includeDotFiles = false): int
    {
        $copied = 0;
        $finder = new Finder();
        $finder->files()->in($sourceDir)->notName($exclude)->ignoreDotFiles(!$includeDotFiles);

        foreach ($finder as $file) {
            $targetPath = $targetDir.'/'.$file->getRelativePathname();

            if (!$overwrite && $this->filesystem->exists($targetPath)) {
                continue;
            }

            $targetFileDir = \dirname($targetPath);

            if (!is_dir($targetFileDir)) {
                $this->filesystem->mkdir($targetFileDir, 0755);
            }

            $this->filesystem->copy($file->getRealPath(), $targetPath, true);
            ++$copied;
        }

        return $copied;
    }

    private function removeEmptyDirectories(string $directory): void
    {
        $finder = new Finder();
        $finder->directories()->in($directory)->sortByName()->reverseSorting();

        foreach ($finder as $dir) {
            if (0 === (new Finder())->in($dir->getRealPath())->depth(0)->count()) {
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
