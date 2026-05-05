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
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ThemeScssFileManager extends ThemeFileManager
{
    /**
     * @var array<string, array<int, array{path: string, name: string, directory: string, isCustom: bool, hasCustom: bool, isCustomOnly: bool}>>
     */
    private array $scssFilesCache = [];

    /**
     * @return array<int, array{path: string, name: string, directory: string, isCustom: bool, hasCustom: bool, isCustomOnly: bool}>
     */
    public function getScssFiles(string $themeName): array
    {
        if (isset($this->scssFilesCache[$themeName])) {
            return $this->scssFilesCache[$themeName];
        }

        /** @var array<int, array{path: string, name: string, directory: string, isCustom: bool, hasCustom: bool, isCustomOnly: bool}> $entries */
        $entries = $this->buildFileEntries($themeName);

        return $this->scssFilesCache[$themeName] = $entries;
    }

    /**
     * Get asset directories (fonts, img, js) for a theme, including custom overrides.
     *
     * @return array<string, list<string>> Map of asset type to source directories (theme first, custom second)
     */
    public function getThemeAssetDirs(string $themeName): array
    {
        $dirs = [];
        $themePath = $this->getThemePath($themeName);

        foreach (['fonts', 'img', 'js'] as $type) {
            $sources = [];

            if ($themePath && is_dir($themePath.'/'.$type)) {
                $sources[] = $themePath.'/'.$type;
            }

            $customDir = $this->projectDir.'/'.$this->customDir.'/'.$type;

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
     * @param list<UploadedFile> $files
     *
     * @return array{familySlug: string, files: list<array{filename: string, relPath: string, format: string}>}
     */
    public function saveUploadedFonts(string $familyName, array $files): array
    {
        $familySlug = $this->normalizeAssetName($familyName);

        if ('' === $familySlug) {
            throw new \InvalidArgumentException('Invalid font family name.');
        }

        $targetDir = $this->projectDir.'/'.$this->customDir.'/fonts/'.$familySlug;
        $this->filesystem->mkdir($targetDir, 0755);

        $savedFiles = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $format = $this->extensionToCssFormat($extension);

            if (null === $format) {
                throw new \InvalidArgumentException(\sprintf('Unsupported font extension: %s', $extension ?: '(none)'));
            }

            $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeBaseName = $this->normalizeAssetName($baseName) ?: 'font';
            $fileName = $this->generateUniqueFontFileName($targetDir, $safeBaseName, $extension);

            $file->move($targetDir, $fileName);

            $savedFiles[] = [
                'filename' => $fileName,
                'relPath' => 'fonts/'.$familySlug.'/'.$fileName,
                'format' => $format,
            ];
        }

        if ([] === $savedFiles) {
            throw new \InvalidArgumentException('No valid font files uploaded.');
        }

        return [
            'familySlug' => $familySlug,
            'files' => $savedFiles,
        ];
    }

    /**
     * @param list<array{filename: string, content: string, format: string}> $files
     *
     * @return array{familySlug: string, files: list<array{filename: string, relPath: string, format: string}>}
     */
    public function saveBinaryFonts(string $familyName, array $files): array
    {
        $familySlug = $this->normalizeAssetName($familyName);

        if ('' === $familySlug) {
            throw new \InvalidArgumentException('Invalid font family name.');
        }

        $targetDir = $this->projectDir.'/'.$this->customDir.'/fonts/'.$familySlug;
        $this->filesystem->mkdir($targetDir, 0755);

        $savedFiles = [];

        foreach ($files as $file) {
            $rawFileName = (string) ($file['filename'] ?? '');
            $format = (string) ($file['format'] ?? '');
            $content = (string) ($file['content'] ?? '');

            if ('' === $rawFileName || '' === $format || '' === $content) {
                continue;
            }

            $extension = strtolower(pathinfo($rawFileName, PATHINFO_EXTENSION) ?: 'woff2');
            $baseName = $this->normalizeAssetName((string) pathinfo($rawFileName, PATHINFO_FILENAME)) ?: 'font';
            $targetFileName = $this->generateUniqueFontFileName($targetDir, $baseName, $extension);

            file_put_contents($targetDir.'/'.$targetFileName, $content);

            $savedFiles[] = [
                'filename' => $targetFileName,
                'relPath' => 'fonts/'.$familySlug.'/'.$targetFileName,
                'format' => $format,
            ];
        }

        if ([] === $savedFiles) {
            throw new \InvalidArgumentException('No valid font files downloaded.');
        }

        return [
            'familySlug' => $familySlug,
            'files' => $savedFiles,
        ];
    }

    public function hasFontFaceDefinition(string $themeName, string $familyName, string $weight, string $style): bool
    {
        $this->validateThemeName($themeName);

        $scssRelativePath = 'base/_fonts.scss';
        $customPath = $this->getCustomFilePath($scssRelativePath);
        $originalPath = $this->getOriginalFilePath($themeName, $scssRelativePath);

        $content = '';
        if (is_file($originalPath)) {
            $content .= (string) file_get_contents($originalPath)."\n";
        }
        if (is_file($customPath)) {
            $content .= (string) file_get_contents($customPath);
        }

        if ('' === trim($content)) {
            return false;
        }

        return 1 === preg_match($this->buildFontFacePattern($familyName, $weight, $style), $content);
    }

    /**
     * Append @font-face block to custom SCSS file.
     *
     * @param list<array{filename: string, relPath: string, format: string}> $files
     */
    public function appendFontFaceToCustomScss(string $themeName, string $familyName, string $weight, string $style, array $files): string
    {
        $this->validateThemeName($themeName);

        $scssRelativePath = 'base/_fonts.scss';
        $scssPath = $this->getCustomFilePath($scssRelativePath);
        $scssDir = \dirname($scssPath);

        if (!is_dir($scssDir)) {
            $this->filesystem->mkdir($scssDir, 0755);
        }

        // Initial state: custom file starts as duplicate of theme file (if it exists)
        if (!is_file($scssPath)) {
            $originalPath = $this->getOriginalFilePath($themeName, $scssRelativePath);
            if (is_file($originalPath)) {
                $this->filesystem->copy($originalPath, $scssPath, true);
            }
        }

        $srcParts = array_map(
            static fn (array $file): string => \sprintf("url('../%s') format('%s')", $file['relPath'], $file['format']),
            $files,
        );

        $block = "@font-face {\n"
            .\sprintf("  font-family: '%s';\n", addslashes($familyName))
            .\sprintf("  font-style: %s;\n", $style)
            .\sprintf("  font-weight: %s;\n", $weight)
            .\sprintf("  src: %s;\n", implode(",\n       ", $srcParts))
            ."  font-display: swap;\n"
            ."}\n";

        $existing = is_file($scssPath) ? (string) file_get_contents($scssPath) : '';

        if (1 === preg_match($this->buildFontFacePattern($familyName, $weight, $style), $existing)) {
            return '';
        }

        $newContent = rtrim($existing)."\n\n".$block."\n";
        file_put_contents($scssPath, ltrim($newContent));

        return $block;
    }

    /**
     * Remove @font-face blocks from custom base/_fonts.scss whose font-family is not
     * referenced anywhere else in SCSS files of the selected theme.
     *
     * @return array{removedBlocks: int, removedFamilies: list<string>, keptBlocks: int}
     */
    public function cleanupUnusedFontFaces(string $themeName): array
    {
        $this->validateThemeName($themeName);

        $fontsRelativePath = 'base/_fonts.scss';
        $customFontsPath = $this->getCustomFilePath($fontsRelativePath);

        $emptyResult = ['removedBlocks' => 0, 'removedFamilies' => [], 'keptBlocks' => 0];

        if (!is_file($customFontsPath)) {
            return $emptyResult;
        }

        $fontsContent = (string) file_get_contents($customFontsPath);

        if ('' === trim($fontsContent)) {
            return $emptyResult;
        }

        preg_match_all('/@font-face\s*\{[^}]*\}\s*/si', $fontsContent, $matches);
        $blocks = $matches[0] ?? [];

        if ([] === $blocks) {
            return $emptyResult;
        }

        $usageHaystack = '';

        foreach ($this->getScssFiles($themeName) as $file) {
            if (($file['path'] ?? '') === $fontsRelativePath) {
                continue;
            }

            $content = $this->getFileContent($themeName, (string) $file['path']);
            if (null !== $content) {
                $usageHaystack .= "\n".$content;
            }
        }

        $keptBlocks = [];
        $removedFamilies = [];

        foreach ($blocks as $block) {
            $family = '';

            if (preg_match('/font-family\s*:\s*[\"\']([^\"\']+)[\"\']/i', $block, $familyMatchQuoted)) {
                $family = trim((string) ($familyMatchQuoted[1] ?? ''));
            } elseif (preg_match('/font-family\s*:\s*([^;\n\r]+)/i', $block, $familyMatchUnquoted)) {
                $rawFamily = trim((string) ($familyMatchUnquoted[1] ?? ''));
                $family = trim((string) preg_split('/\s*,\s*/', $rawFamily)[0], " \t\n\r\0\x0B\"'");
            }

            if ('' === $family) {
                $keptBlocks[] = $block;
                continue;
            }

            if ($this->isFontFamilyReferenced($usageHaystack, $family)) {
                $keptBlocks[] = $block;
                continue;
            }

            $removedFamilies[] = $family;
        }

        $newContent = $fontsContent;

        foreach ($blocks as $block) {
            $newContent = str_replace($block, '', $newContent);
        }

        if ([] !== $keptBlocks) {
            $newContent = rtrim($newContent)."\n\n".implode("\n", array_map('rtrim', $keptBlocks))."\n";
        } else {
            $newContent = trim($newContent)."\n";
        }

        file_put_contents($customFontsPath, $newContent);

        return [
            'removedBlocks' => \count($removedFamilies),
            'removedFamilies' => array_values(array_unique($removedFamilies)),
            'keptBlocks' => \count($keptBlocks),
        ];
    }

    /**
     * Get all non-partial (entry point) SCSS files for a theme. Returns files without
     * leading underscore at depth 0 from both theme and custom directory.
     *
     * @return array<string, string>
     */
    public function getEntryPointFiles(string $themeName): array
    {
        $files = [];

        $themePath = $this->getThemePath($themeName);

        foreach ([$themePath ? $themePath.'/'.$this->getAssetSubDir() : null, $this->getCustomDirPath()] as $base) {
            if (!$base || !is_dir($base)) {
                continue;
            }

            $finder = new Finder();
            $finder->files()->in($base)->name('*.scss')->depth(0)->sortByName();

            foreach ($finder as $file) {
                if (!str_starts_with($file->getFilename(), '_')) {
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
    public function getEntryPointPath(string $themeName, string $fileName): string|null
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
    public function getDefaultScssPath(string $themeName): string|null
    {
        return $this->getEntryPointPath($themeName, 'default.scss');
    }

    protected function getAssetSubDir(): string
    {
        return 'scss';
    }

    protected function themeQualifies(string $themePath): bool
    {
        return is_dir($themePath.'/'.$this->getAssetSubDir());
    }

    protected function configureFileFinder(Finder $finder): void
    {
        $finder->name('*.scss');
    }

    private function generateUniqueFontFileName(string $targetDir, string $baseName, string $extension): string
    {
        $fileName = $baseName.'.'.$extension;
        $counter = 1;

        while ($this->filesystem->exists($targetDir.'/'.$fileName)) {
            ++$counter;
            $fileName = \sprintf('%s-%d.%s', $baseName, $counter, $extension);
        }

        return $fileName;
    }

    private function buildFontFacePattern(string $familyName, string $weight, string $style): string
    {
        $escapedFamily = preg_quote(addslashes($familyName), '/');
        $escapedWeight = preg_quote($weight, '/');
        $escapedStyle = preg_quote($style, '/');

        return '/@font-face\s*\{[^}]*font-family\s*:\s*[\"\']?'.$escapedFamily.'[\"\']?\s*;[^}]*font-style\s*:\s*'.$escapedStyle.'\s*;[^}]*font-weight\s*:\s*'.$escapedWeight.'\s*;[^}]*\}/is';
    }

    private function normalizeAssetName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9\-_]+/', '-', $name) ?? '';

        return trim($name, '-_');
    }

    private function extensionToCssFormat(string $extension): string|null
    {
        return match ($extension) {
            'woff2' => 'woff2',
            'woff' => 'woff',
            'ttf' => 'truetype',
            'otf' => 'opentype',
            default => null,
        };
    }

    private function isFontFamilyReferenced(string $scssContent, string $family): bool
    {
        $quotedFamily = preg_quote($family, '/');
        $unquotedFamily = preg_quote(trim($family, " \t\n\r\0\x0B\"'"), '/');

        $declarationPatterns = [
            '/font-family\s*:\s*[^;]*([\"\'])'.$quotedFamily.'\\1[^;]*;/iu',
            '/font-family\s*:\s*[^;]*\b'.$unquotedFamily.'\b[^;]*;/iu',
            '/--[a-z0-9\-_]*font-family[a-z0-9\-_]*\s*:\s*[^;]*([\"\'])'.$quotedFamily.'\\1[^;]*;/iu',
            '/--[a-z0-9\-_]*font-family[a-z0-9\-_]*\s*:\s*[^;]*\b'.$unquotedFamily.'\b[^;]*;/iu',
        ];

        foreach ($declarationPatterns as $pattern) {
            if (1 === preg_match($pattern, $scssContent)) {
                return true;
            }
        }

        return false;
    }
}
