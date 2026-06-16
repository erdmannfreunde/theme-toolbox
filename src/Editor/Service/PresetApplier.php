<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\Editor\Service;

use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssCompiler;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Validates a preset against the registry, applies the server-side contrast
 * safeguard, and persists it by editing the custom _variables.scss in place —
 * each changed token's value is replaced where it is declared (a property that
 * does not exist yet is added to the first :root{} block), exactly as a developer
 * would edit the file. Only the values the user actually changed are written, so
 * untouched var()-based tokens keep their expression. Applying recompiles the theme.
 */
class PresetApplier
{
    private const VARIABLES_SCSS = '_variables.scss';

    private const SYSTEM_FONT_STACK = 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';

    public function __construct(
        private readonly TokenRegistry $registry,
        private readonly ContrastGuard $contrastGuard,
        private readonly ThemeScssFileManager $fileManager,
        private readonly ThemeScssCompiler $compiler,
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * Validate, clamp and contrast-correct an incoming preset against the registry. Unknown
     * properties are dropped, out-of-range values clamped, invalid colours discarded.
     *
     * @param array<string, mixed> $preset
     *
     * @return array{values: array<string, string>, corrected: bool}
     */
    public function sanitize(array $preset, string|null $theme = null): array
    {
        $map = $this->registry->getTokenMap($theme);
        $clean = [];

        foreach ($preset as $property => $value) {
            if (!\is_string($property) || !isset($map[$property])) {
                continue;
            }

            $sanitized = $this->sanitizeValue($map[$property], $value);

            if (null !== $sanitized) {
                $clean[$property] = $sanitized;
            }
        }

        $result = $this->contrastGuard->correctPreset($clean);

        return ['values' => $result['preset'], 'corrected' => $result['corrected']];
    }

    /**
     * Persist a preset for a theme: edit the custom _variables.scss in place and recompile.
     *
     * @param array<string, mixed> $preset
     *
     * @return array{values: array<string, string>, corrected: bool}
     */
    public function apply(string $theme, array $preset): array
    {
        $sanitized = $this->sanitize($preset, $theme);

        if ([] !== $sanitized['values']) {
            $content = $this->readVariables($theme) ?? '';

            foreach ($sanitized['values'] as $property => $value) {
                $content = $this->setValue($content, $property, $value);
            }

            $this->writeVariables($content);
            $this->compiler->compile($theme);
        }

        return $sanitized;
    }

    /**
     * Replace a property's value in place, or add it to the first :root{}/html{}
     * block (or a new :root{} block) when it does not exist yet.
     */
    private function setValue(string $content, string $property, string $value): string
    {
        $pattern = $this->declarationPattern($property);

        if (preg_match($pattern, $content)) {
            return (string) preg_replace_callback(
                $pattern,
                static fn (array $m): string => $m[1].$value.$m[3],
                $content,
                1,
            );
        }

        $declaration = \sprintf("\n  %s: %s;", $property, $value);

        if (preg_match('/(?::root|html)\s*\{/', $content)) {
            return (string) preg_replace_callback(
                '/((?::root|html)\s*\{)/',
                static fn (array $m): string => $m[1].$declaration,
                $content,
                1,
            );
        }

        return rtrim($content)."\n\n:root {".$declaration."\n}\n";
    }

    private function declarationPattern(string $property): string
    {
        return '/(?<![\w-])('.preg_quote($property, '/').'\s*:\s*)([^;]*)(;)/';
    }

    /**
     * Current custom _variables.scss content, falling back to the theme original
     * (copy-on-write) so the managed values always live in the imported, custom file.
     */
    private function readVariables(string $theme): string|null
    {
        $path = $this->fileManager->getCustomFilePath(self::VARIABLES_SCSS);

        if (is_file($path)) {
            return (string) file_get_contents($path);
        }

        return $this->fileManager->getOriginalFileContent($theme, self::VARIABLES_SCSS);
    }

    private function writeVariables(string $content): void
    {
        $path = $this->fileManager->getCustomFilePath(self::VARIABLES_SCSS);
        $dir = \dirname($path);

        if (!is_dir($dir)) {
            $this->filesystem->mkdir($dir, 0755);
        }

        $this->filesystem->dumpFile($path, $content);
    }

    /**
     * @param array<string, mixed> $token
     */
    private function sanitizeValue(array $token, mixed $value): string|null
    {
        $type = (string) ($token['type'] ?? 'text');

        if (\is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        return match ($type) {
            'color' => $this->contrastGuard->isHex($value) ? $this->contrastGuard->normalizeHex($value) : null,
            'length' => $this->sanitizeLength($token, $value),
            'select' => $this->sanitizeSelect($token, $value),
            'font' => $this->sanitizeFont($value),
            default => $this->sanitizeText($value),
        };
    }

    /**
     * @param array<string, mixed> $token
     */
    private function sanitizeLength(array $token, string $value): string|null
    {
        if (1 !== preg_match('/^(-?\d*\.?\d+)\s*([a-z%]*)$/i', $value, $m)) {
            return null;
        }

        $number = (float) $m[1];
        $unit = (string) ($token['unit'] ?? $m[2]);

        if (isset($token['min']) && $number < (float) $token['min']) {
            $number = (float) $token['min'];
        }

        if (isset($token['max']) && $number > (float) $token['max']) {
            $number = (float) $token['max'];
        }

        $formatted = rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');

        return ('' === $formatted ? '0' : $formatted).$unit;
    }

    /**
     * @param array<string, mixed> $token
     */
    private function sanitizeSelect(array $token, string $value): string|null
    {
        $options = array_map(static fn ($o): string => (string) $o, (array) ($token['options'] ?? []));

        return \in_array($value, $options, true) ? $value : null;
    }

    private function sanitizeFont(string $value): string|null
    {
        if ('' === $value || 'system' === strtolower($value)) {
            return self::SYSTEM_FONT_STACK;
        }

        // Allow only characters valid inside a font-family declaration.
        $clean = preg_replace('/[^a-zA-Z0-9 ,\'"\-]/', '', $value);
        $clean = trim((string) $clean);

        return '' === $clean ? null : mb_substr($clean, 0, 200);
    }

    private function sanitizeText(string $value): string|null
    {
        $clean = preg_replace('/[{}<>;]/', '', $value);
        $clean = trim((string) $clean);

        return '' === $clean ? null : mb_substr($clean, 0, 200);
    }
}
