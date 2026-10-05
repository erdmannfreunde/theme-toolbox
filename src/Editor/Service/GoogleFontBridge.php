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

use ErdmannFreunde\ThemeToolboxBundle\Service\GoogleFontsService;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssCompiler;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;

/**
 * Adapter onto the existing Google-Fonts download function of the toolbox. The
 * editor never invents a new font mechanism: a chosen Google font is downloaded
 * server-side, stored self-hosted under layout/custom/fonts and registered as a
 * font face — exactly like the backend theme file editor. The visitor browser
 * only ever sees the self-hosted file (DSGVO by design).
 */
class GoogleFontBridge
{
    private const SYSTEM_FONT_STACK = 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';

    public function __construct(
        private readonly GoogleFontsService $googleFontsService,
        private readonly ThemeScssFileManager $fileManager,
        private readonly ThemeScssCompiler $compiler,
        private readonly TokenRegistry $registry,
    ) {
    }

    /**
     * Search the Google-Fonts catalogue of the existing toolbox function.
     *
     * @return list<array{family: string, category: string, variants: list<string>}>
     */
    public function searchCatalog(string $search = '', string $category = '', int $limit = 25): array
    {
        return $this->googleFontsService->getCatalog($search, $category, $limit);
    }

    /**
     * Self-host a Google font family (downloads the regular + bold weights that
     * exist, registers @font-face) and return the CSS family value to apply plus the
     * self-hosted woff2 faces so the editor can load them live via the FontFace API.
     *
     * The @font-face lands in base/_fonts.scss for persistence; recompiling syncs the
     * woff2 into the public assets/<theme>/fonts directory so it is servable
     * immediately (without waiting for the next page render).
     *
     * The weights come from the theme (see TokenRegistry::getFontWeights());
     * $extraWeights adds the ones a single request needs on top, typically the chosen
     * heading weight.
     *
     * @param list<string> $extraWeights
     *
     * @return array{family: string, value: string, weights: list<string>, faces: list<array{weight: string, path: string}>}
     *
     * @throws \RuntimeException if no weight could be downloaded
     */
    public function import(string $theme, string $family, array $extraWeights = []): array
    {
        $family = trim($family);

        if ('' === $family) {
            throw new \RuntimeException('Empty font family.');
        }

        $imported = [];
        $faces = [];
        $lastError = null;

        foreach ($this->resolveWeights($theme, $extraWeights) as $weight) {
            if ($this->fileManager->hasFontFaceDefinition($theme, $family, $weight, 'normal')) {
                $imported[] = $weight;
                continue;
            }

            try {
                $downloaded = $this->googleFontsService->downloadFontFiles($family, $weight, 'normal');
                $saved = $this->fileManager->saveBinaryFonts($family, $downloaded['files']);
                $this->fileManager->appendFontFaceToCustomScss($theme, $family, $weight, 'normal', $saved['files']);
                $imported[] = $weight;

                foreach ($saved['files'] as $file) {
                    if ('woff2' === ($file['format'] ?? '')) {
                        $faces[] = ['weight' => $weight, 'path' => (string) $file['relPath']];
                    }
                }
            } catch (\RuntimeException|\InvalidArgumentException $e) {
                $lastError = $e;
            }
        }

        if ([] === $imported) {
            throw new \RuntimeException($lastError?->getMessage() ?? \sprintf('Font "%s" could not be downloaded.', $family));
        }

        // Recompile so the new @font-face + woff2 are synced into the public assets
        // directory (live FontFace load + persistence on the next render).
        if ([] !== $faces) {
            $this->compiler->compile($theme);
        }

        return [
            'family' => $family,
            'value' => \sprintf('"%s", %s', $family, self::SYSTEM_FONT_STACK),
            'weights' => $imported,
            'faces' => $faces,
        ];
    }

    /**
     * The theme's weights plus the extra ones, deduplicated and sorted.
     *
     * @param list<string> $extraWeights
     *
     * @return list<string>
     */
    private function resolveWeights(string $theme, array $extraWeights): array
    {
        $weights = array_unique([...$this->registry->getFontWeights($theme), ...$extraWeights]);
        sort($weights, SORT_NUMERIC);

        return array_values($weights);
    }
}
