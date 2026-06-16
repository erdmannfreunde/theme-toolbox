/*
 * Live-Theme-Editor — frontend logic. Builds the edit mask from the inline
 * registry, applies changes live to the real page via CSS custom properties,
 * warns on low contrast, and (authenticated only) persists via the apply route.
 *
 * Only tokens the user actually changes are persisted; untouched tokens keep
 * their var()-inheritance instead of being frozen to a resolved value (§15.5).
 */
(function boot() {
    'use strict';

    // This file is emitted via TL_JAVASCRIPT, which Contao renders *before* the
    // TL_BODY markup (dock, panel, inline data). Wait for the DOM so those nodes
    // exist before wiring anything.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
        return;
    }

    var SYSTEM_STACK = 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
    var Guard = window.ToolboxContrastGuard;

    var dataEl = document.getElementById('toolbox-editor-data');
    if (!dataEl) {
        return;
    }

    var DATA;
    try {
        DATA = JSON.parse(dataEl.textContent);
    } catch (e) {
        return;
    }

    var REGISTRY = DATA.registry || {};
    var TOKENS = REGISTRY.tokens || [];
    var GROUPS = REGISTRY.groups || [];
    var PRESETS = REGISTRY.presets || [];
    var FONTS = (REGISTRY.fonts || []).slice();
    var ROOT = document.documentElement;

    // Mount the chrome in a Shadow DOM so the host theme cannot style the editor
    // (and vice versa). Live editing is unaffected: it writes CSS custom
    // properties to document.documentElement (light DOM), which the theme reads.
    var template = document.getElementById('toolbox-editor-template');
    if (!template) {
        return;
    }

    var host = document.createElement('div');
    host.id = 'toolbox-editor-host';
    host.setAttribute('style', 'all: initial; visibility: hidden');
    var shadow = host.attachShadow({ mode: 'open' });

    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = deriveAssetUrl('editor.css');
    var reveal = function () { host.style.visibility = 'visible'; };
    link.addEventListener('load', reveal);
    link.addEventListener('error', reveal);
    window.setTimeout(reveal, 1500);
    shadow.appendChild(link);

    shadow.appendChild(template.content.cloneNode(true));
    document.body.appendChild(host);

    function deriveAssetUrl(file) {
        var s = document.querySelector('script[src*="erdmannfreundethemetoolbox/editor.js"]');
        if (s && s.src) {
            return s.src.replace(/editor\.js(\?.*)?$/, file);
        }
        return 'bundles/erdmannfreundethemetoolbox/' + file;
    }

    var editor = shadow.getElementById('tt-editor');
    var dock = shadow.getElementById('tt-dock');
    if (!editor || !dock) {
        return;
    }

    var panelEdit = shadow.getElementById('tt-panel-edit');
    var presetsEl = shadow.getElementById('tt-presets');

    // Properties changed during this session — only these get persisted.
    var dirty = {};

    // In public/demo mode there is no server write; instead the visitor's changes
    // are kept per browser session in sessionStorage so a reload doesn't lose them.
    var isDemo = DATA.publicMode === true;
    var STORAGE_KEY = 'tt-editor-demo-' + (DATA.theme || 'theme');

    function saveDemoState() {
        if (!isDemo) {
            return;
        }
        try {
            window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(dirty));
        } catch (e) {
            // sessionStorage may be unavailable (e.g. private mode) — ignore.
        }
    }

    function loadDemoState() {
        if (!isDemo) {
            return;
        }
        var saved;
        try {
            saved = JSON.parse(window.sessionStorage.getItem(STORAGE_KEY) || 'null');
        } catch (e) {
            saved = null;
        }
        if (!saved || typeof saved !== 'object') {
            return;
        }
        Object.keys(saved).forEach(function (prop) {
            if (typeof prop === 'string' && prop.indexOf('--') === 0) {
                var value = String(saved[prop]);
                ROOT.style.setProperty(prop, value);
                dirty[prop] = value;
            }
        });
    }

    function clearDemoState() {
        try {
            window.sessionStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            // ignore
        }
    }

    /* ---------- helpers ---------- */

    function computed(prop) {
        var inline = ROOT.style.getPropertyValue(prop);
        if (inline) {
            return inline.trim();
        }
        return getComputedStyle(ROOT).getPropertyValue(prop).trim();
    }

    // Resolve a colour custom property to a concrete computed colour via a hidden
    // probe in the light DOM (the browser resolves var() chains, hsl(),
    // color-mix(), etc.). Returns null when the property is not defined.
    var colorProbe;
    function resolveColor(prop) {
        if (!colorProbe) {
            colorProbe = document.createElement('span');
            colorProbe.setAttribute('style', 'position:absolute;width:0;height:0;visibility:hidden;pointer-events:none');
            document.body.appendChild(colorProbe);
        }
        colorProbe.style.color = '';
        colorProbe.style.color = 'var(' + prop + ', transparent)';
        var resolved = getComputedStyle(colorProbe).color;
        if (!resolved || resolved === 'transparent' || resolved === 'rgba(0, 0, 0, 0)') {
            return null;
        }
        return resolved;
    }

    // Normalise any browser-computed colour string to #rrggbb by painting it on a
    // 1×1 canvas and reading the pixel back. This is format-agnostic: rgb(),
    // color(srgb …) (0–1 floats), color-mix(), hsl(), oklab(), named colours —
    // all become real 0–255 RGB, unlike string-parsing which mis-reads floats.
    var paintCtx;
    function colorToHex(colorString) {
        if (!paintCtx) {
            var canvas = document.createElement('canvas');
            canvas.width = 1;
            canvas.height = 1;
            paintCtx = canvas.getContext('2d', { willReadFrequently: true });
        }
        paintCtx.fillStyle = '#000000';
        paintCtx.fillStyle = colorString;
        paintCtx.fillRect(0, 0, 1, 1);
        var d = paintCtx.getImageData(0, 0, 1, 1).data;
        return '#' + [d[0], d[1], d[2]].map(function (v) {
            return v.toString(16).padStart(2, '0');
        }).join('');
    }

    function colorHex(prop, fallback) {
        var resolved = resolveColor(prop);
        if (resolved) {
            return colorToHex(resolved);
        }
        return fallback ? colorToHex(String(fallback)) : '';
    }

    // Effective starting value for a control: the computed page value (which
    // already reflects the persisted theme SCSS) → registry default.
    function seedValue(token) {
        var live = computed(token.property);
        if (live) {
            return live;
        }
        return token.default != null ? String(token.default) : '';
    }

    function applyLive(prop, value) {
        ROOT.style.setProperty(prop, value);
        dirty[prop] = value;
        saveDemoState();
    }

    function unitOf(token) {
        return token.unit != null ? token.unit : '';
    }

    /* ---------- control builders ---------- */

    function buildRow(token) {
        var row = document.createElement('div');
        row.className = 'tt-row';
        row.dataset.prop = token.property;

        var label = document.createElement('div');
        label.className = 'tt-row-label';

        var lbl = document.createElement('span');
        lbl.className = 'tt-lbl';
        lbl.textContent = token.label || token.property;
        label.appendChild(lbl);

        if (token.type === 'length') {
            var chip = document.createElement('span');
            chip.className = 'tt-val-chip';
            chip.dataset.chip = '1';
            label.appendChild(chip);
        } else {
            var prop = document.createElement('span');
            prop.className = 'tt-prop';
            prop.textContent = token.property;
            label.appendChild(prop);
        }

        row.appendChild(label);

        if (token.type === 'color') {
            buildColor(row, token);
        } else if (token.type === 'length') {
            buildLength(row, token);
        } else if (token.type === 'select') {
            buildSelect(row, token);
        } else if (token.type === 'font') {
            buildFont(row, token);
        } else {
            buildText(row, token);
        }

        return row;
    }

    function buildColor(row, token) {
        var hex = colorHex(token.property, token.default) || '#000000';
        var wrap = document.createElement('div');
        wrap.className = 'tt-color-ctl';

        var swatch = document.createElement('span');
        swatch.className = 'tt-swatch';
        swatch.style.background = hex;

        var picker = document.createElement('input');
        picker.type = 'color';
        picker.value = /^#[0-9a-f]{6}$/i.test(hex) ? hex : '#000000';
        swatch.appendChild(picker);

        var text = document.createElement('input');
        text.type = 'text';
        text.className = 'tt-hex';
        text.value = hex.toUpperCase();

        wrap.appendChild(swatch);
        wrap.appendChild(text);
        row.appendChild(wrap);

        function set(value) {
            applyLive(token.property, value);
            swatch.style.background = value;
            text.value = value.toUpperCase();
            picker.value = value;
            checkContrast();
        }

        picker.addEventListener('input', function (e) {
            set(e.target.value);
        });
        text.addEventListener('change', function (e) {
            if (Guard.isHex(e.target.value)) {
                set(Guard.normalizeHex(e.target.value));
            }
        });
    }

    function buildLength(row, token) {
        var unit = unitOf(token);
        var seed = parseFloat(seedValue(token));
        if (isNaN(seed)) {
            seed = parseFloat(token.default) || 0;
        }

        var range = document.createElement('input');
        range.type = 'range';
        range.min = token.min;
        range.max = token.max;
        range.step = token.step;
        range.value = seed;
        row.appendChild(range);

        var chip = row.querySelector('[data-chip]');
        if (chip) {
            chip.textContent = seed + unit;
        }

        range.addEventListener('input', function (e) {
            var value = e.target.value + unit;
            applyLive(token.property, value);
            if (chip) {
                chip.textContent = value;
            }
        });
    }

    function buildSelect(row, token) {
        var seed = seedValue(token);
        var select = document.createElement('select');
        (token.options || []).forEach(function (opt) {
            var option = document.createElement('option');
            option.value = String(opt);
            option.textContent = String(opt);
            if (String(opt) === String(parseInt(seed, 10))) {
                option.selected = true;
            }
            select.appendChild(option);
        });
        row.appendChild(select);

        select.addEventListener('change', function (e) {
            applyLive(token.property, e.target.value);
        });
    }

    function buildFont(row, token) {
        var seed = seedValue(token);
        var wrap = document.createElement('div');
        wrap.className = 'tt-font-ctl';

        var select = document.createElement('select');
        select.dataset.fontSelect = '1';
        fillFontOptions(select, seed);
        wrap.appendChild(select);

        if (DATA.canPersist) {
            var add = document.createElement('button');
            add.type = 'button';
            add.className = 'tt-font-add';
            add.title = 'Google-Schrift laden';
            add.textContent = '+';
            add.addEventListener('click', function () {
                openFontLoader(token.property, select);
            });
            wrap.appendChild(add);
        }

        row.appendChild(wrap);

        select.addEventListener('change', function (e) {
            applyLive(token.property, e.target.value);
        });
    }

    // The first concrete family of a font value, or null for a system / generic
    // stack (system-ui, sans-serif, …). Used both to seed the picker correctly
    // and to detect non-self-hosted families on import.
    function primaryFamily(value) {
        value = String(value || '').trim();
        if (!value) {
            return null;
        }
        var quoted = value.match(/^(['"])(.*?)\1/);
        var first = quoted ? quoted[2] : value.split(',')[0].trim();
        if (/^(system-ui|-apple-system|blinkmacsystemfont|ui-(sans-serif|serif|monospace|rounded)|sans-serif|serif|monospace|cursive|fantasy|inherit|initial|unset|revert)$/i.test(first)) {
            return null;
        }
        return first;
    }

    function fillFontOptions(select, seed) {
        select.innerHTML = '';
        // Match on the primary family, not a substring: a system stack contains
        // fallbacks like "Roboto" that must not select the Roboto option.
        var primary = primaryFamily(seed);
        var matched = false;
        FONTS.forEach(function (font) {
            var option = document.createElement('option');
            option.value = font.value;
            option.textContent = font.name;
            var isMatch = font.name === 'System'
                ? (!!seed && !primary)
                : (!!primary && font.name === primary);
            if (isMatch) {
                option.selected = true;
                matched = true;
            }
            select.appendChild(option);
        });
        if (!matched && select.options.length) {
            select.options[0].selected = true;
        }
    }

    function buildText(row, token) {
        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'tt-hex';
        input.value = seedValue(token);
        row.appendChild(input);
        input.addEventListener('change', function (e) {
            applyLive(token.property, e.target.value);
        });
    }

    /* ---------- font loader (authenticated) ---------- */

    var fontLoaderDebounce = null;

    function openFontLoader(prop, select) {
        var existing = shadow.getElementById('tt-font-loader');
        if (existing) {
            existing.remove();
        }

        var box = document.createElement('div');
        box.id = 'tt-font-loader';
        box.style.cssText = 'margin:.5rem 0;display:flex;gap:.4rem';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'tt-hex';
        input.placeholder = 'Google-Schrift, z. B. Inter';
        input.setAttribute('list', 'tt-font-suggest');

        var datalist = document.createElement('datalist');
        datalist.id = 'tt-font-suggest';

        var load = document.createElement('button');
        load.type = 'button';
        load.className = 'tt-ea tt-ea-primary';
        load.style.flex = '0 0 auto';
        load.textContent = 'Laden';

        box.appendChild(input);
        box.appendChild(datalist);
        box.appendChild(load);
        select.parentNode.insertAdjacentElement('afterend', box);
        input.focus();

        input.addEventListener('input', function () {
            window.clearTimeout(fontLoaderDebounce);
            var term = input.value.trim();
            if (term.length < 2) {
                return;
            }
            fontLoaderDebounce = window.setTimeout(function () {
                fetchCatalog(term, datalist);
            }, 300);
        });

        load.addEventListener('click', function () {
            var family = input.value.trim();
            if (!family) {
                return;
            }
            load.textContent = 'Lädt …';
            load.disabled = true;
            downloadFont(family, function (ok, result) {
                if (ok) {
                    // Register the self-hosted woff2 with the browser before
                    // applying, otherwise the page falls back to a system font
                    // until the next reload (the compiled CSS isn't reloaded live).
                    loadFontFaces(result.family, result.faces || [], function () {
                        addFont(result.family, result.value);
                        applyLive(prop, result.value);
                        box.remove();
                    });
                } else {
                    load.textContent = 'Fehlgeschlagen';
                    window.setTimeout(function () {
                        load.textContent = 'Laden';
                        load.disabled = false;
                    }, 1600);
                }
            });
        });
    }

    function loadFontFaces(family, faces, done) {
        if (!window.FontFace || !faces.length) {
            done();
            return;
        }
        var pending = faces.map(function (face) {
            try {
                var ff = new FontFace(family, 'url(' + face.url + ')', {
                    weight: String(face.weight || '400'),
                    display: 'swap'
                });
                return ff.load().then(function (loaded) {
                    document.fonts.add(loaded);
                }).catch(function () {});
            } catch (e) {
                return Promise.resolve();
            }
        });
        Promise.all(pending).then(done).catch(done);
    }

    function fetchCatalog(term, datalist) {
        if (!DATA.routes || !DATA.routes.fontCatalog) {
            return;
        }
        fetch(DATA.routes.fontCatalog + '?search=' + encodeURIComponent(term) + '&limit=8', {
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json();
        }).then(function (json) {
            if (!json.ok) {
                return;
            }
            datalist.innerHTML = '';
            (json.fonts || []).forEach(function (font) {
                var option = document.createElement('option');
                option.value = font.family;
                datalist.appendChild(option);
            });
        }).catch(function () {});
    }

    function downloadFont(family, done) {
        if (!DATA.routes || !DATA.routes.fontDownload) {
            done(false);
            return;
        }
        var body = new FormData();
        body.append('theme', DATA.theme);
        body.append('family', family);
        body.append('REQUEST_TOKEN', DATA.token);
        fetch(DATA.routes.fontDownload, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (r) {
            return r.json();
        }).then(function (json) {
            done(!!json.ok, json);
        }).catch(function () {
            done(false);
        });
    }

    function addFont(name, value) {
        if (!FONTS.some(function (f) { return f.value === value; })) {
            FONTS.push({ name: name, value: value });
        }
        shadow.querySelectorAll('[data-font-select]').forEach(function (select) {
            var current = select.value;
            fillFontOptions(select, current);
        });
    }

    /* ---------- mask assembly ---------- */

    function buildMask() {
        panelEdit.innerHTML = '';

        GROUPS.forEach(function (group) {
            var groupTokens = TOKENS.filter(function (t) { return t.group === group.key; });
            if (!groupTokens.length) {
                return;
            }

            var el = document.createElement('div');
            el.className = 'tt-group';

            var head = document.createElement('div');
            head.className = 'tt-group-head';
            head.innerHTML = '<h3></h3><span class="tt-chev">▾</span>';
            head.querySelector('h3').textContent = group.label || group.key;

            var body = document.createElement('div');
            body.className = 'tt-group-body';

            groupTokens.filter(function (t) { return t.core; }).forEach(function (t) {
                body.appendChild(buildRow(t));
            });

            if (group.key === 'colors') {
                var warn = document.createElement('div');
                warn.className = 'tt-warn';
                warn.id = 'tt-contrast-warn';
                warn.innerHTML = '⚠ <span><b>Kontrast zu niedrig</b> — <span id="tt-ratio"></span></span>';
                body.appendChild(warn);
            }

            var advTokens = groupTokens.filter(function (t) { return !t.core; });
            if (advTokens.length) {
                var adv = document.createElement('div');
                adv.className = 'tt-advanced';
                advTokens.forEach(function (t) {
                    adv.appendChild(buildRow(t));
                });
                body.appendChild(adv);
            }

            el.appendChild(head);
            el.appendChild(body);
            head.addEventListener('click', function () {
                el.classList.toggle('tt-collapsed');
            });
            panelEdit.appendChild(el);
        });

        var advBtn = document.createElement('button');
        advBtn.type = 'button';
        advBtn.className = 'tt-adv-toggle';
        advBtn.textContent = 'Erweiterte Einstellungen anzeigen';
        advBtn.addEventListener('click', function () {
            var open = !panelEdit.querySelector('.tt-advanced.tt-show');
            panelEdit.querySelectorAll('.tt-advanced').forEach(function (a) {
                a.classList.toggle('tt-show', open);
            });
            advBtn.textContent = open ? 'Erweiterte Einstellungen ausblenden' : 'Erweiterte Einstellungen anzeigen';
        });
        panelEdit.appendChild(advBtn);
    }

    /* ---------- contrast warning ---------- */

    function checkContrast() {
        var warn = shadow.getElementById('tt-contrast-warn');
        if (!warn) {
            return;
        }
        var text = colorHex('--color-text', null);
        var bg = colorHex('--color-page-background', null);
        if (!Guard.isHex(text) || !Guard.isHex(bg)) {
            warn.classList.remove('tt-show');
            return;
        }
        var r = Guard.ratio(text, bg);
        if (r < Guard.MIN_TEXT) {
            warn.classList.add('tt-show');
            shadow.getElementById('tt-ratio').textContent = r.toFixed(1) + ':1 (empfohlen ≥ 4,5:1)';
        } else {
            warn.classList.remove('tt-show');
        }
    }

    /* ---------- presets ---------- */

    function buildPresets() {
        presetsEl.innerHTML = '';
        if (!PRESETS.length) {
            var empty = document.createElement('div');
            empty.className = 'tt-empty';
            empty.textContent = 'Für dieses Theme sind keine Vorlagen hinterlegt.';
            presetsEl.appendChild(empty);
            return;
        }

        var swatchKeys = REGISTRY.swatches || {};

        PRESETS.forEach(function (preset) {
            var card = document.createElement('button');
            card.type = 'button';
            card.className = 'tt-preset';

            // Up to three chips from the swatch tokens the preset actually sets,
            // in a preferred role order (handles themes that use "secondary").
            var chips = document.createElement('div');
            chips.className = 'tt-preset-chips';
            var shown = 0;
            ['primary', 'secondary', 'accent', 'text'].forEach(function (role) {
                if (shown >= 3) {
                    return;
                }
                var prop = swatchKeys[role];
                if (!prop || !preset.values[prop]) {
                    return;
                }
                var chip = document.createElement('span');
                chip.style.background = preset.values[prop];
                chips.appendChild(chip);
                shown++;
            });

            var meta = document.createElement('div');
            var name = document.createElement('div');
            name.className = 'tt-preset-name';
            name.textContent = preset.name;
            var sub = document.createElement('div');
            sub.className = 'tt-preset-sub';
            sub.textContent = preset.description || '';
            meta.appendChild(name);
            meta.appendChild(sub);

            card.appendChild(chips);
            card.appendChild(meta);
            card.addEventListener('click', function () {
                applyPreset(preset.values);
            });
            presetsEl.appendChild(card);
        });
    }

    function applyPreset(values) {
        Object.keys(values).forEach(function (prop) {
            applyLive(prop, String(values[prop]));
        });
        buildMask();
        checkContrast();
        switchTab('edit');
    }

    /* ---------- tabs / mode / dock ---------- */

    function switchTab(tab) {
        editor.querySelectorAll('.tt-tab').forEach(function (b) {
            b.classList.toggle('tt-active', b.dataset.tab === tab);
        });
        editor.querySelectorAll('.tt-panel').forEach(function (p) {
            p.classList.toggle('tt-active', p.id === 'tt-panel-' + tab);
        });
    }

    editor.querySelectorAll('.tt-tab').forEach(function (b) {
        b.addEventListener('click', function () {
            switchTab(b.dataset.tab);
        });
    });

    function open() {
        editor.hidden = false;
        // next frame so the transition runs
        window.requestAnimationFrame(function () {
            editor.classList.add('tt-open');
        });
        dock.hidden = true;
    }

    function close() {
        editor.classList.remove('tt-open');
        dock.hidden = false;
    }

    dock.addEventListener('click', open);
    shadow.getElementById('tt-close').addEventListener('click', close);

    /* ---------- footer actions ---------- */

    function buildPayload() {
        // Only the tokens the user actually changed this session are persisted;
        // they get edited in place in _variables.scss. Untouched tokens are left
        // alone so their var()-based expressions stay intact.
        var payload = {};
        Object.keys(dirty).forEach(function (prop) {
            payload[prop] = String(dirty[prop]);
        });
        return payload;
    }

    function exportJson() {
        return JSON.stringify(buildPayload(), null, 2);
    }

    function openExport() {
        shadow.getElementById('tt-export-json').textContent = exportJson();
        var sub = shadow.getElementById('tt-modal-sub');
        sub.textContent = editor.dataset.mode === 'public'
            ? 'Diese Werte kannst du nach dem Kauf in der Toolbox importieren.'
            : 'Diese Werte kannst du als JSON sichern und später wieder importieren.';
        shadow.getElementById('tt-modal-bg').classList.add('tt-show');
    }

    shadow.getElementById('tt-export-primary').addEventListener('click', openExport);
    shadow.getElementById('tt-close-modal').addEventListener('click', function () {
        shadow.getElementById('tt-modal-bg').classList.remove('tt-show');
    });
    shadow.getElementById('tt-copy-json').addEventListener('click', function (e) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(exportJson());
        }
        e.target.textContent = 'Kopiert ✓';
        window.setTimeout(function () { e.target.textContent = 'Kopieren'; }, 1400);
    });

    /* ---------- import (buyer mode) ---------- */

    var importMissingFonts = [];

    function isFontAvailable(family) {
        return FONTS.some(function (f) { return f.name === family; });
    }

    function missingFontsInImport(values) {
        var fontProps = {};
        TOKENS.forEach(function (t) { if (t.type === 'font') { fontProps[t.property] = true; } });
        var missing = [];
        Object.keys(values).forEach(function (prop) {
            if (!fontProps[prop]) { return; }
            var fam = primaryFamily(values[prop]);
            if (fam && !isFontAvailable(fam) && missing.indexOf(fam) === -1) {
                missing.push(fam);
            }
        });
        return missing;
    }

    function openImport() {
        shadow.getElementById('tt-import-json').value = '';
        shadow.getElementById('tt-import-error').hidden = true;
        shadow.getElementById('tt-import-fonts').hidden = true;
        shadow.getElementById('tt-import-bg').classList.add('tt-show');
    }

    function closeImport() {
        shadow.getElementById('tt-import-bg').classList.remove('tt-show');
    }

    function applyImport() {
        var parsed;
        try {
            parsed = JSON.parse(shadow.getElementById('tt-import-json').value);
        } catch (e) {
            parsed = null;
        }
        if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
            shadow.getElementById('tt-import-error').hidden = false;
            return;
        }
        shadow.getElementById('tt-import-error').hidden = true;

        // Apply only known registry tokens; the rest would be dropped on persist.
        var known = {};
        TOKENS.forEach(function (t) { known[t.property] = true; });
        Object.keys(parsed).forEach(function (prop) {
            if (typeof prop === 'string' && prop.indexOf('--') === 0 && known[prop]) {
                applyLive(prop, String(parsed[prop]));
            }
        });
        buildMask();
        checkContrast();
        switchTab('edit');

        // Offer to self-host any imported font that is not available yet
        // (only when the buyer can actually download — the route is gated).
        importMissingFonts = DATA.canPersist ? missingFontsInImport(parsed) : [];
        if (importMissingFonts.length) {
            shadow.getElementById('tt-import-fonts-msg').textContent =
                'Diese Schriften sind noch nicht vorhanden: ' + importMissingFonts.join(', ')
                + '. Jetzt self-hosted laden?';
            var loadBtn = shadow.getElementById('tt-import-fonts-load');
            loadBtn.disabled = false;
            loadBtn.textContent = 'Schriften laden';
            shadow.getElementById('tt-import-fonts').hidden = false;
        } else {
            closeImport();
        }
    }

    function importLoadFonts() {
        var btn = shadow.getElementById('tt-import-fonts-load');
        btn.disabled = true;
        btn.textContent = 'Lädt …';
        var remaining = importMissingFonts.length;
        var failed = 0;
        var finish = function () {
            remaining--;
            if (remaining > 0) {
                return;
            }
            if (failed) {
                btn.textContent = 'Teilweise fehlgeschlagen';
                btn.disabled = false;
            } else {
                btn.textContent = 'Geladen ✓';
                window.setTimeout(closeImport, 900);
            }
        };
        importMissingFonts.forEach(function (family) {
            downloadFont(family, function (ok, result) {
                if (ok) {
                    loadFontFaces(result.family, result.faces || [], function () {
                        addFont(result.family, result.value);
                        finish();
                    });
                } else {
                    failed++;
                    finish();
                }
            });
        });
    }

    var importBtn = shadow.getElementById('tt-import');
    if (importBtn) {
        importBtn.addEventListener('click', openImport);
    }
    shadow.getElementById('tt-import-apply').addEventListener('click', applyImport);
    shadow.getElementById('tt-import-close').addEventListener('click', closeImport);
    shadow.getElementById('tt-import-fonts-load').addEventListener('click', importLoadFonts);

    shadow.getElementById('tt-reset').addEventListener('click', function () {
        Object.keys(dirty).forEach(function (prop) {
            ROOT.style.removeProperty(prop);
        });
        dirty = {};
        clearDemoState();
        buildMask();
        checkContrast();
    });

    var applyBtn = shadow.getElementById('tt-apply');
    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            if (!DATA.canPersist || !DATA.routes || !DATA.routes.apply) {
                return;
            }
            applyBtn.disabled = true;
            var body = new FormData();
            body.append('theme', DATA.theme);
            body.append('preset', JSON.stringify(buildPayload()));
            body.append('REQUEST_TOKEN', DATA.token);
            fetch(DATA.routes.apply, {
                method: 'POST',
                credentials: 'same-origin',
                body: body
            }).then(function (r) {
                return r.json();
            }).then(function (json) {
                applyBtn.disabled = false;
                if (json.ok) {
                    dirty = {};
                    applyBtn.textContent = json.corrected ? 'Übernommen (korrigiert)' : 'Übernommen ✓';
                    window.setTimeout(function () { applyBtn.textContent = 'Übernehmen'; }, 1800);
                } else {
                    applyBtn.textContent = 'Fehler';
                    window.setTimeout(function () { applyBtn.textContent = 'Übernehmen'; }, 1800);
                }
            }).catch(function () {
                applyBtn.disabled = false;
                applyBtn.textContent = 'Fehler';
                window.setTimeout(function () { applyBtn.textContent = 'Übernehmen'; }, 1800);
            });
        });
    }

    /* ---------- init ---------- */

    loadDemoState();
    buildMask();
    buildPresets();
    checkContrast();
})();
