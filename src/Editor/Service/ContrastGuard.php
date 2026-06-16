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

/**
 * WCAG contrast checks and conservative auto-correction. Pure algorithm, no
 * dependency. Mirrors public/contrast-guard.js bit for bit (same relative
 * luminance, same ratio formula) so both ports agree on the test table.
 *
 * In Phase 1 the auto-correction is only used as a server-side safeguard when a
 * preset is persisted. The manual editor only warns (never auto-corrects).
 */
class ContrastGuard
{
    public const MIN_TEXT = 4.5;

    public const MIN_BORDER = 1.5;

    /**
     * Contrast pairs evaluated server-side. Each entry: foreground property,
     * background property, minimum ratio, and whether the foreground may be
     * auto-corrected (only text colours are corrected; brand/border stay).
     */
    private const PAIRS = [
        ['fg' => '--color-text', 'bg' => '--color-page-background', 'min' => self::MIN_TEXT, 'correct' => true],
        ['fg' => '--color-gray', 'bg' => '--color-page-background', 'min' => self::MIN_TEXT, 'correct' => true],
        ['fg' => '--color-page-background', 'bg' => '--color-brand', 'min' => self::MIN_TEXT, 'correct' => false],
        ['fg' => '--base-border-color', 'bg' => '--color-page-background', 'min' => self::MIN_BORDER, 'correct' => false],
    ];

    /**
     * WCAG relative luminance of a hex colour (0..1).
     */
    public function luminance(string $hex): float
    {
        [$r, $g, $b] = $this->toRgb($hex);

        $channel = static function (int $value): float {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    /**
     * WCAG contrast ratio between two hex colours (1..21).
     */
    public function ratio(string $hexA, string $hexB): float
    {
        $l1 = $this->luminance($hexA);
        $l2 = $this->luminance($hexB);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    public function passes(string $foreground, string $background, float $min = self::MIN_TEXT): bool
    {
        return $this->ratio($foreground, $background) >= $min;
    }

    /**
     * Nudge a foreground colour in 5 % HSL lightness steps away from the background until
     * the ratio is met (max 10 iterations), then fall back to near-black / pure white.
     */
    public function autoCorrect(string $foreground, string $background, float $min = self::MIN_TEXT): string
    {
        if ($this->passes($foreground, $background, $min)) {
            return $this->normalizeHex($foreground);
        }

        [$h, $s, $l] = $this->toHsl($foreground);
        $darken = $this->luminance($background) > 0.5;

        for ($i = 0; $i < 10; ++$i) {
            $l = $darken ? max(0.0, $l - 0.05) : min(1.0, $l + 0.05);
            $candidate = $this->hslToHex($h, $s, $l);

            if ($this->passes($candidate, $background, $min)) {
                return $candidate;
            }
        }

        return $darken ? '#1a1a1a' : '#ffffff';
    }

    /**
     * Evaluate the standard contrast pairs of a preset (real custom property map).
     *
     * @param array<string, string> $preset
     *
     * @return list<array{pair: string, ratio: float, min: float, ok: bool}>
     */
    public function check(array $preset): array
    {
        $report = [];

        foreach (self::PAIRS as $pair) {
            $fg = $preset[$pair['fg']] ?? null;
            $bg = $preset[$pair['bg']] ?? null;

            if (!\is_string($fg) || !\is_string($bg) || !$this->isHex($fg) || !$this->isHex($bg)) {
                continue;
            }

            $ratio = $this->ratio($fg, $bg);

            $report[] = [
                'pair' => $pair['fg'].' / '.$pair['bg'],
                'ratio' => round($ratio, 2),
                'min' => $pair['min'],
                'ok' => $ratio >= $pair['min'],
            ];
        }

        return $report;
    }

    /**
     * Apply the safeguard correction to a preset: only correctable foreground colours are
     * adjusted. Returns the (possibly) corrected preset and whether anything changed.
     *
     * @param array<string, string> $preset
     *
     * @return array{preset: array<string, string>, corrected: bool}
     */
    public function correctPreset(array $preset): array
    {
        $corrected = false;

        foreach (self::PAIRS as $pair) {
            if (!$pair['correct']) {
                continue;
            }

            $fg = $preset[$pair['fg']] ?? null;
            $bg = $preset[$pair['bg']] ?? null;

            if (!\is_string($fg) || !\is_string($bg) || !$this->isHex($fg) || !$this->isHex($bg)) {
                continue;
            }

            if ($this->passes($fg, $bg, $pair['min'])) {
                continue;
            }

            $fixed = $this->autoCorrect($fg, $bg, $pair['min']);

            if (0 !== strcasecmp($fixed, $this->normalizeHex($fg))) {
                $preset[$pair['fg']] = $fixed;
                $corrected = true;
            }
        }

        return ['preset' => $preset, 'corrected' => $corrected];
    }

    /**
     * Derive a small, WCAG-aware palette from a single brand colour without KI.
     *
     * @return array<string, string>
     */
    public function derivePalette(string $brandHex): array
    {
        $brand = $this->normalizeHex($brandHex);
        $background = '#ffffff';
        $text = $this->autoCorrect('#222222', $background);
        $muted = $this->autoCorrect('#555555', $background);

        [$h, $s, $l] = $this->toHsl($brand);
        $highlight = $this->hslToHex($h, min(1.0, $s + 0.05), max(0.0, $l - 0.08));

        return [
            '--color-brand' => $brand,
            '--color-highlight' => $highlight,
            '--color-text' => $text,
            '--color-gray' => $muted,
            '--color-page-background' => $background,
            '--button-background-hover' => $highlight,
            '--links-color-hover' => $highlight,
        ];
    }

    public function isHex(string $value): bool
    {
        return 1 === preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', trim($value));
    }

    public function normalizeHex(string $hex): string
    {
        $hex = strtolower(trim($hex));

        if (1 === preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $hex, $m)) {
            return '#'.$m[1].$m[1].$m[2].$m[2].$m[3].$m[3];
        }

        return $hex;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function toRgb(string $hex): array
    {
        $hex = $this->normalizeHex($hex);

        if (1 !== preg_match('/^#([0-9a-f]{6})$/i', $hex)) {
            // Defensive fallback for non-hex input; treated as black.
            return [0, 0, 0];
        }

        $int = (int) hexdec(substr($hex, 1));

        return [($int >> 16) & 0xFF, ($int >> 8) & 0xFF, $int & 0xFF];
    }

    /**
     * @return array{0: float, 1: float, 2: float} hue 0..360, sat/light 0..1
     */
    private function toHsl(string $hex): array
    {
        [$r, $g, $b] = array_map(static fn (int $v): float => $v / 255, $this->toRgb($hex));

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;

        if (0.0 === $d) {
            return [0.0, 0.0, $l];
        }

        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h * 60, $s, $l];
    }

    private function hslToHex(float $h, float $s, float $l): string
    {
        $h = fmod($h, 360) / 360;

        if (0.0 === $s) {
            $v = (int) round($l * 255);

            return $this->rgbToHex($v, $v, $v);
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        $r = $this->hueToRgb($p, $q, $h + 1 / 3);
        $g = $this->hueToRgb($p, $q, $h);
        $b = $this->hueToRgb($p, $q, $h - 1 / 3);

        return $this->rgbToHex(
            (int) round($r * 255),
            (int) round($g * 255),
            (int) round($b * 255),
        );
    }

    private function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            ++$t;
        }

        if ($t > 1) {
            --$t;
        }

        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }

        if ($t < 1 / 2) {
            return $q;
        }

        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }

    private function rgbToHex(int $r, int $g, int $b): string
    {
        return \sprintf('#%02x%02x%02x', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
    }
}
