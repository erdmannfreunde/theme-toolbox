/*
 * ContrastGuard — JS port. Mirrors src/Editor/Service/ContrastGuard.php bit for
 * bit (same relative luminance, same ratio formula) so both ports agree on the
 * shared colour-pair test table. In the manual editor this only drives live
 * warnings; it never auto-corrects (§8).
 */
(function (window) {
    'use strict';

    function normalizeHex(value) {
        var hex = String(value || '').trim().toLowerCase();
        var short = /^#([0-9a-f])([0-9a-f])([0-9a-f])$/i.exec(hex);
        if (short) {
            return '#' + short[1] + short[1] + short[2] + short[2] + short[3] + short[3];
        }
        return hex;
    }

    function isHex(value) {
        return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(String(value || '').trim());
    }

    function toRgb(hex) {
        hex = normalizeHex(hex);
        if (!/^#[0-9a-f]{6}$/i.test(hex)) {
            return [0, 0, 0];
        }
        var int = parseInt(hex.slice(1), 16);
        return [(int >> 16) & 0xff, (int >> 8) & 0xff, int & 0xff];
    }

    function luminance(hex) {
        var rgb = toRgb(hex).map(function (value) {
            var c = value / 255;
            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
    }

    function ratio(hexA, hexB) {
        var l1 = luminance(hexA);
        var l2 = luminance(hexB);
        return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    }

    function passes(foreground, background, min) {
        return ratio(foreground, background) >= (min || 4.5);
    }

    window.ToolboxContrastGuard = {
        MIN_TEXT: 4.5,
        MIN_BORDER: 1.5,
        normalizeHex: normalizeHex,
        isHex: isHex,
        luminance: luminance,
        ratio: ratio,
        passes: passes
    };
})(window);
