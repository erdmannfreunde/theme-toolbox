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

use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;

/**
 * Builds the merged token registry that drives the editor mask.
 *
 * The basis registry ships with the bundle (Resources/tokens.json). A theme may
 * ship its own tokens.json (layout/<theme>/tokens.json) with additional or
 * overriding tokens; the registry merges both. Presets live in
 * layout/<theme>/presets/*.json and reference real custom properties directly.
 */
class TokenRegistry
{
    private const FONTS_SCSS = 'base/_fonts.scss';

    private const SYSTEM_FONT_VALUE = 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $cache = [];

    public function __construct(
        private readonly ThemeScssFileManager $fileManager,
        private readonly string|null $basisRegistryPath = null,
    ) {
    }

    /**
     * Name of the active theme (first available layout theme), or null.
     */
    public function getActiveTheme(): string|null
    {
        return array_key_first($this->fileManager->getAvailableThemes()) ?: null;
    }

    /**
     * The full registry payload consumed by the editor frontend.
     *
     * @return array{tokens: list<array<string, mixed>>, groups: list<array<string, mixed>>, presets: list<array<string, mixed>>, fonts: list<array<string, string>>, swatches: array<string, string>}
     */
    public function toArray(string|null $theme = null, bool $publicMode = false): array
    {
        $theme ??= $this->getActiveTheme();
        $cacheKey = ($theme ?? '_none').($publicMode ? ':public' : ':auth');

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $basis = $this->loadBasis();
        $groups = $basis['groups'];
        $tokens = $basis['tokens'];

        if (null !== $theme) {
            [$groups, $tokens] = $this->mergeThemeTokens($theme, $groups, $tokens);
        }

        // A theme may hide a basis token (e.g. a derived var() property it does not want
        // edited directly) by merging "hidden": true onto it.
        $tokens = array_values(array_filter($tokens, static fn (array $t): bool => empty($t['hidden'])));

        $swatches = [];

        foreach ($tokens as $token) {
            if (isset($token['swatch']) && \is_string($token['swatch'])) {
                $swatches[$token['swatch']] = (string) $token['property'];
            }
        }

        $payload = [
            'tokens' => array_values($tokens),
            'groups' => array_values($groups),
            'presets' => null !== $theme ? $this->loadPresets($theme) : [],
            'fonts' => null !== $theme ? $this->loadFonts($theme) : $this->systemFontsOnly(),
            'swatches' => $swatches,
        ];

        return $this->cache[$cacheKey] = $payload;
    }

    /**
     * Flat map property => token definition for the active/merged registry.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getTokenMap(string|null $theme = null): array
    {
        $map = [];

        foreach ($this->toArray($theme)['tokens'] as $token) {
            $map[(string) $token['property']] = $token;
        }

        return $map;
    }

    /**
     * @return array{groups: list<array<string, mixed>>, tokens: list<array<string, mixed>>}
     */
    private function loadBasis(): array
    {
        $path = $this->basisRegistryPath ?? __DIR__.'/../Resources/tokens.json';
        $data = $this->decodeFile($path);

        return [
            'groups' => array_values((array) ($data['groups'] ?? [])),
            'tokens' => array_values((array) ($data['tokens'] ?? [])),
        ];
    }

    /**
     * @param list<array<string, mixed>> $groups
     * @param list<array<string, mixed>> $tokens
     *
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function mergeThemeTokens(string $theme, array $groups, array $tokens): array
    {
        $themePath = $this->fileManager->getThemePath($theme);

        if (null === $themePath) {
            return [$groups, $tokens];
        }

        $themeRegistry = $themePath.'/tokens.json';

        if (!is_file($themeRegistry)) {
            return [$groups, $tokens];
        }

        // Optional, author-controlled file: a typo must not 500 the page render. Degrade
        // to the basis tokens, like loadPresets() does for broken presets.
        $data = $this->decodeFile($themeRegistry, false);

        if ([] === $data) {
            return [$groups, $tokens];
        }

        // Merge groups by key (theme additions appended).
        $groupKeys = array_map(static fn (array $g): string => (string) ($g['key'] ?? ''), $groups);

        foreach ((array) ($data['groups'] ?? []) as $group) {
            $key = (string) ($group['key'] ?? '');

            if ('' !== $key && !\in_array($key, $groupKeys, true)) {
                $groups[] = $group;
                $groupKeys[] = $key;
            }
        }

        // Merge tokens by property (theme overrides basis, additions appended).
        $byProperty = [];

        foreach ($tokens as $token) {
            $byProperty[(string) $token['property']] = $token;
        }

        foreach ((array) ($data['tokens'] ?? []) as $token) {
            $property = (string) ($token['property'] ?? '');

            if ('' === $property) {
                continue;
            }

            $byProperty[$property] = isset($byProperty[$property])
                ? array_merge($byProperty[$property], $token)
                : $token;
        }

        return [$groups, array_values($byProperty)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadPresets(string $theme): array
    {
        $themePath = $this->fileManager->getThemePath($theme);

        if (null === $themePath) {
            return [];
        }

        $presetsDir = $themePath.'/presets';

        if (!is_dir($presetsDir)) {
            return [];
        }

        $presets = [];
        $files = glob($presetsDir.'/*.json') ?: [];
        sort($files);

        foreach ($files as $file) {
            $data = $this->decodeFile($file, false);

            if (!\is_array($data) || [] === $data) {
                continue;
            }

            $values = $data['values'] ?? $data;

            if (!\is_array($values) || [] === $values) {
                continue;
            }

            $presets[] = [
                'name' => (string) ($data['name'] ?? pathinfo($file, PATHINFO_FILENAME)),
                'description' => (string) ($data['description'] ?? ''),
                'values' => $this->onlyCustomProperties($values),
            ];
        }

        return $presets;
    }

    /**
     * Self-hosted font families available for the theme (system always first).
     *
     * @return list<array<string, string>>
     */
    private function loadFonts(string $theme): array
    {
        $fonts = $this->systemFontsOnly();

        $content = '';

        $customContent = $this->fileManager->getFileContent($theme, self::FONTS_SCSS, true);
        if (null !== $customContent) {
            $content .= "\n".$customContent;
        }

        $originalContent = $this->fileManager->getOriginalFileContent($theme, self::FONTS_SCSS);
        if (null !== $originalContent) {
            $content .= "\n".$originalContent;
        }

        if ('' === trim($content)) {
            return $fonts;
        }

        preg_match_all('/@font-face\s*\{[^}]*font-family\s*:\s*[\'"]?([^\'";]+)[\'"]?\s*;/is', $content, $matches);

        $seen = ['System' => true];

        foreach ($matches[1] ?? [] as $rawFamily) {
            $family = trim((string) $rawFamily, " \t\n\r\0\x0B\"'");

            if ('' === $family || isset($seen[$family])) {
                continue;
            }

            $seen[$family] = true;
            $fonts[] = [
                'name' => $family,
                'value' => \sprintf('"%s", %s', $family, self::SYSTEM_FONT_VALUE),
            ];
        }

        return $fonts;
    }

    /**
     * @return list<array<string, string>>
     */
    private function systemFontsOnly(): array
    {
        return [[
            'name' => 'System',
            'value' => self::SYSTEM_FONT_VALUE,
        ]];
    }

    /**
     * Drop any keys that are not real custom properties (defensive).
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, string>
     */
    private function onlyCustomProperties(array $values): array
    {
        $clean = [];

        foreach ($values as $property => $value) {
            if (\is_string($property) && str_starts_with($property, '--') && (\is_string($value) || is_numeric($value))) {
                $clean[$property] = (string) $value;
            }
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeFile(string $path, bool $required = true): array
    {
        if (!is_file($path)) {
            if ($required) {
                throw new \RuntimeException(\sprintf('Token registry file not found: %s', $path));
            }

            return [];
        }

        $json = (string) file_get_contents($path);
        $data = json_decode($json, true);

        if (!\is_array($data)) {
            if ($required) {
                throw new \RuntimeException(\sprintf('Invalid JSON in token registry file: %s', $path));
            }

            return [];
        }

        return $data;
    }
}
