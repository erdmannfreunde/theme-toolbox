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

use Psr\Log\LoggerInterface;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;
use Symfony\Component\Filesystem\Filesystem;

class ThemeScssCompiler
{
    private const OUTPUT_DIR = 'assets/css';

    /** @var array<string, string|null> */
    private array $compiledPaths = [];

    public function __construct(
        private readonly ThemeScssFileManager $fileManager,
        private readonly string $projectDir,
        private readonly Filesystem $filesystem,
        private readonly bool $debugMode = false,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Compile the default.scss for a theme and return the path to the compiled CSS.
     */
    public function compile(string $themeName): ?string
    {
        if (\array_key_exists($themeName, $this->compiledPaths)) {
            return $this->compiledPaths[$themeName];
        }

        $defaultScssPath = $this->fileManager->getDefaultScssPath($themeName);

        if (!$defaultScssPath) {
            return $this->compiledPaths[$themeName] = null;
        }

        $outputDir = $this->projectDir . '/' . self::OUTPUT_DIR;
        $outputFile = $outputDir . '/' . $themeName . '.css';

        // Check if recompilation is needed using file modification times
        if ($this->filesystem->exists($outputFile) && !$this->needsRecompilation($themeName, $outputFile)) {
            return $this->compiledPaths[$themeName] = $outputFile;
        }

        // Ensure output directory exists
        if (!is_dir($outputDir)) {
            $this->filesystem->mkdir($outputDir, 0755);
        }

        // Compile SCSS
        $compiler = new Compiler();
        $compiler->setOutputStyle($this->debugMode ? OutputStyle::EXPANDED : OutputStyle::COMPRESSED);

        // Get the base SCSS directory for the theme
        $scssDir = \dirname($defaultScssPath);

        // Set up import paths - ALL resolution goes through our callback
        // This ensures custom files are always checked first
        $compiler->setImportPaths([
            fn (string $path) => $this->resolveImportPath($themeName, $path, $scssDir),
        ]);

        try {
            $scssContent = file_get_contents($defaultScssPath);
            $result = $compiler->compileString($scssContent);
            $css = $result->getCss();

            // Save compiled CSS
            file_put_contents($outputFile, $css);

            return $this->compiledPaths[$themeName] = $outputFile;
        } catch (\Exception $e) {
            $this->logger?->error('SCSS compilation failed for theme "{theme}": {error}', [
                'theme' => $themeName,
                'error' => $e->getMessage(),
            ]);

            return $this->compiledPaths[$themeName] = null;
        }
    }

    /**
     * Get the web-accessible path for the compiled CSS.
     */
    public function getWebPath(string $themeName): ?string
    {
        $compiledPath = $this->compile($themeName);

        if (!$compiledPath) {
            return null;
        }

        // Return relative path from web root
        return str_replace($this->projectDir . '/', '', $compiledPath);
    }

    /**
     * Resolve import paths, preferring custom files over originals.
     */
    private function resolveImportPath(string $themeName, string $path, string $scssDir): ?string
    {
        // Normalize path (add .scss if needed)
        $normalizedPath = $this->normalizeImportPath($path);

        // Handle relative paths with ../
        if (str_contains($path, '../')) {
            // Resolve relative to the original SCSS directory
            $absolutePath = realpath($scssDir . '/' . $normalizedPath);
            if ($absolutePath && $this->filesystem->exists($absolutePath)) {
                return $absolutePath;
            }

            $partialPath = $this->getPartialPath($normalizedPath);
            $absolutePartialPath = realpath($scssDir . '/' . $partialPath);
            if ($absolutePartialPath && $this->filesystem->exists($absolutePartialPath)) {
                return $absolutePartialPath;
            }

            return null;
        }

        // First check custom directory
        $customPath = $this->fileManager->getCustomFilePath($normalizedPath);

        if ($this->filesystem->exists($customPath)) {
            return $customPath;
        }

        // Also check with underscore prefix for partials
        $partialPath = $this->getPartialPath($normalizedPath);
        $customPartialPath = $this->fileManager->getCustomFilePath($partialPath);

        if ($this->filesystem->exists($customPartialPath)) {
            return $customPartialPath;
        }

        // Fall back to original directory
        $originalPath = $this->fileManager->getOriginalFilePath($themeName, $normalizedPath);

        if ($this->filesystem->exists($originalPath)) {
            return $originalPath;
        }

        $originalPartialPath = $this->fileManager->getOriginalFilePath($themeName, $partialPath);

        if ($this->filesystem->exists($originalPartialPath)) {
            return $originalPartialPath;
        }

        return null;
    }

    /**
     * Normalize import path by adding .scss extension if missing.
     */
    private function normalizeImportPath(string $path): string
    {
        // Remove leading ./ if present
        $path = preg_replace('#^\./#', '', $path);

        // Add .scss extension if not present
        if (!str_ends_with($path, '.scss') && !str_ends_with($path, '.css')) {
            $path .= '.scss';
        }

        return $path;
    }

    /**
     * Get the partial path (with underscore prefix).
     */
    private function getPartialPath(string $path): string
    {
        $dir = \dirname($path);
        $filename = basename($path);

        if (!str_starts_with($filename, '_')) {
            $filename = '_' . $filename;
        }

        if ($dir === '.') {
            return $filename;
        }

        return $dir . '/' . $filename;
    }

    /**
     * Check if recompilation is needed based on file modification times.
     */
    private function needsRecompilation(string $themeName, string $outputFile): bool
    {
        $outputMtime = filemtime($outputFile);

        if ($outputMtime === false) {
            return true;
        }

        $files = $this->fileManager->getScssFiles($themeName);

        foreach ($files as $file) {
            $customMtime = @filemtime($this->fileManager->getCustomFilePath($file['path']));

            if ($customMtime !== false && $customMtime > $outputMtime) {
                return true;
            }

            $originalMtime = @filemtime($this->fileManager->getOriginalFilePath($themeName, $file['path']));

            if ($originalMtime !== false && $originalMtime > $outputMtime) {
                return true;
            }
        }

        // Also check custom directory for new files
        $customDir = $this->fileManager->getCustomDirPath();

        if (is_dir($customDir)) {
            $customDirMtime = filemtime($customDir);

            if ($customDirMtime !== false && $customDirMtime > $outputMtime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear the cache for a theme.
     */
    public function clearCache(string $themeName): void
    {
        $cssFile = $this->projectDir . '/' . self::OUTPUT_DIR . '/' . $themeName . '.css';

        if ($this->filesystem->exists($cssFile)) {
            $this->filesystem->remove($cssFile);
        }
    }

    /**
     * Clear all compiled CSS files.
     */
    public function clearAllCache(): void
    {
        $outputDir = $this->projectDir . '/' . self::OUTPUT_DIR;

        if (!is_dir($outputDir)) {
            return;
        }

        $themes = $this->fileManager->getAvailableThemes();

        foreach (array_keys($themes) as $themeName) {
            $this->clearCache($themeName);
        }
    }
}
