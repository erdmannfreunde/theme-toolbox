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

    /**
     * Keywords that never denote a downloadable family.
     */
    private const GENERIC_FAMILIES = [
        'system-ui', '-apple-system', 'blinkmacsystemfont', 'sans-serif', 'serif',
        'monospace', 'cursive', 'fantasy', 'ui-sans-serif', 'ui-serif', 'ui-monospace',
        'ui-rounded', 'inherit', 'initial', 'unset', 'revert',
    ];

    private const SYSTEM_FONT_VALUE = 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';

    /**
     * Fallback when neither the basis nor the theme declares a usable list.
     */
    private const DEFAULT_FONT_WEIGHTS = ['400', '700'];

    private const HEADINGS_WEIGHT = '--headings-font-weight';

    private const HEADINGS_FAMILY = '--headings-font-family';

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
     * @return array{tokens: list<array<string, mixed>>, groups: list<array<string, mixed>>, presets: list<array<string, mixed>>, fonts: list<array<string, mixed>>, swatches: array<string, string>, fontWeights: list<string>}
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
            'fontWeights' => $this->resolveFontWeights($theme),
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
     * The font weights to self-host for this theme.
     *
     * The basis registry declares the default; a theme's tokens.json may declare its
     * own "fontWeights", which replaces the list rather than extending it.
     *
     * @return list<string>
     */
    public function getFontWeights(string|null $theme = null): array
    {
        return $this->toArray($theme)['fontWeights'];
    }

    /**
     * The weights the editor offers for --headings-font-weight, used to validate an
     * incoming request before anything is downloaded.
     *
     * @return list<string>
     */
    public function getHeadingsWeightOptions(string|null $theme = null): array
    {
        $options = $this->getTokenMap($theme)[self::HEADINGS_WEIGHT]['options'] ?? [];

        return \is_array($options) ? $this->normalizeFontWeights($options) : [];
    }

    /**
     * Which families to import for the given values, and the extra weights each one
     * needs on top of the theme's list.
     *
     * The heading weight only attaches to the family that --headings-font-family
     * names, and only when that family is being imported anyway — otherwise a
     * theme-shipped font would be looked up at Google.
     *
     * @param array<string, mixed> $values
     * @param string|null          $headingsWeight Fallback when $values does not set it
     *
     * @return array<string, list<string>> family as spelled => extra weights
     */
    public function collectFontImports(string|null $theme, array $values, string|null $headingsWeight = null): array
    {
        $imports = [];

        foreach ($this->collectFontFamilies($theme, $values) as $family) {
            $imports[$family] = [];
        }

        $headingsFamily = $this->primaryFamily((string) ($values[self::HEADINGS_FAMILY] ?? ''));

        if (null === $headingsFamily || !isset($imports[$headingsFamily])) {
            return $imports;
        }

        $weight = $values[self::HEADINGS_WEIGHT] ?? $headingsWeight;
        $weight = $this->normalizeFontWeights(null === $weight ? [] : [$weight]);

        if ([] !== $weight) {
            $imports[$headingsFamily] = $weight;
        }

        return $imports;
    }

    /**
     * Every family the presets of a theme ask for, with the extra weights they need.
     *
     * @return array<string, list<string>> family as spelled => extra weights
     */
    public function getPresetFontImports(string $theme): array
    {
        $imports = [];
        $spelling = [];

        foreach ($this->loadPresets($theme) as $preset) {
            foreach ($this->collectFontImports($theme, $preset['values']) as $family => $weights) {
                $key = strtolower($family);
                // First spelling wins, so the result does not depend on how many presets happen
                // to repeat the family.
                $spelling[$key] ??= $family;
                $imports[$key] = array_values(array_unique([...$imports[$key] ?? [], ...$weights]));
                sort($imports[$key], SORT_STRING);
            }
        }

        $result = [];

        foreach ($imports as $key => $weights) {
            $result[$spelling[$key]] = $weights;
        }

        return $result;
    }

    /**
     * The font families every preset of a theme asks for, each one once.
     *
     * Used to preload the fonts for the public-mode editor, which never persists and
     * therefore never downloads anything on its own.
     *
     * @return list<string>
     */
    public function getPresetFontFamilies(string $theme): array
    {
        return array_keys($this->getPresetFontImports($theme));
    }

    /**
     * The leading family of every font stack in the given values, generics dropped.
     *
     * Only the first entry of a stack is a real choice; everything behind it is the
     * fallback chain and must never trigger a download.
     *
     * @param array<string, mixed> $values
     *
     * @return list<string>
     */
    public function collectFontFamilies(string|null $theme, array $values): array
    {
        $map = $this->getTokenMap($theme);
        $families = [];

        foreach ($values as $property => $value) {
            if (!\is_string($value) || 'font' !== ($map[$property]['type'] ?? null)) {
                continue;
            }

            $first = $this->primaryFamily($value);

            if (null !== $first) {
                $families[strtolower($first)] ??= $first;
            }
        }

        return array_values($families);
    }

    /**
     * The leading family of a font stack, or null for a generic keyword.
     */
    private function primaryFamily(string $value): string|null
    {
        $first = trim(trim(explode(',', $value)[0]), '\'"');

        if ('' === $first || \in_array(strtolower($first), self::GENERIC_FAMILIES, true)) {
            return null;
        }

        return $first;
    }

    /**
     * @return list<string>
     */
    private function resolveFontWeights(string|null $theme): array
    {
        $weights = $this->normalizeFontWeights($this->readDeclaredFontWeights($theme));

        return [] !== $weights ? $weights : self::DEFAULT_FONT_WEIGHTS;
    }

    /**
     * The raw list as declared: the theme's, if it declares one, otherwise the basis one.
     *
     * @return array<int|string, mixed>
     */
    private function readDeclaredFontWeights(string|null $theme): array
    {
        if (null !== $theme && null !== ($themePath = $this->fileManager->getThemePath($theme))) {
            $themeData = $this->decodeFile($themePath.'/tokens.json', false);

            if (\is_array($themeData['fontWeights'] ?? null)) {
                return $themeData['fontWeights'];
            }
        }

        $basis = $this->decodeFile($this->basisRegistryPath ?? __DIR__.'/../Resources/tokens.json', false);

        return \is_array($basis['fontWeights'] ?? null) ? $basis['fontWeights'] : [];
    }

    /**
     * Whole hundreds from 100 to 900, deduplicated and sorted. Anything else is dropped.
     *
     * @param array<int|string, mixed> $raw
     *
     * @return list<string>
     */
    private function normalizeFontWeights(array $raw): array
    {
        $weights = [];

        foreach ($raw as $value) {
            if (!\is_int($value) && !(\is_string($value) && 1 === preg_match('/^\d+$/', $value))) {
                continue;
            }

            $weight = (int) $value;

            if ($weight < 100 || $weight > 900 || 0 !== $weight % 100) {
                continue;
            }

            $weights[$weight] = true;
        }

        $weights = array_keys($weights);
        sort($weights);

        return array_map(static fn (int $w): string => (string) $w, $weights);
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
        $seen = ['System' => true];

        // Custom first: a family declared there is a self-hosted import and may be
        // extended with further weights, unlike the ones the theme ships itself.
        // hasCustomFile() first, because getFileContent() falls back to the theme's own
        // file when no custom one exists — which would mark everything as an import.
        $sources = [
            true => $this->fileManager->hasCustomFile(self::FONTS_SCSS)
                ? $this->fileManager->getFileContent($theme, self::FONTS_SCSS, true)
                : null,
            false => $this->fileManager->getOriginalFileContent($theme, self::FONTS_SCSS),
        ];

        foreach ($sources as $isCustom => $content) {
            if (null === $content || '' === trim($content)) {
                continue;
            }

            preg_match_all('/@font-face\s*\{[^}]*font-family\s*:\s*[\'"]?([^\'";]+)[\'"]?\s*;/is', $content, $matches);

            foreach ($matches[1] ?? [] as $rawFamily) {
                $family = trim((string) $rawFamily, " \t\n\r\0\x0B\"'");

                if ('' === $family || isset($seen[$family])) {
                    continue;
                }

                $seen[$family] = true;
                $fonts[] = [
                    'name' => $family,
                    'value' => \sprintf('"%s", %s', $family, self::SYSTEM_FONT_VALUE),
                    'custom' => (bool) $isCustom,
                ];
            }
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
            'custom' => false,
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
