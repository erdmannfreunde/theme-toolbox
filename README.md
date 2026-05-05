[![Build Status](https://travis-ci.org/erdmannfreunde/theme-toolbox.svg)](https://travis-ci.org/erdmannfreunde/theme-toolbox)
[![Latest Version tagged](http://img.shields.io/github/tag/erdmannfreunde/theme-toolbox.svg)](https://github.com/erdmannfreunde/theme-toolbox/tags)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/erdmannfreunde/theme-toolbox.svg)](https://packagist.org/packages/https://github.com/erdmannfreunde/theme-toolbox/tags)
[![Installations via composer per month](https://img.shields.io/packagist/dm/erdmannfreunde/theme-toolbox.svg)](https://packagist.org/packages/erdmannfreunde/theme-toolbox)

# Theme Toolbox

Dieses Paket enthält hilfreiche Tools zur Arbeit mit den [Contao Themes][1] von [Erdmann & Freunde][2].

## 1. CSS-Klassen-Auswahl

Wenn du deinen Kunden keine Liste von Klassennamen für Varianten und spezifische Stile geben möchtest, kannst du die Theme-Toolbox verwenden, um menschenlesbare Stile zu Elementen, Modulen und Artikeln hinzuzufügen. Im Toolbox-Editor kannst du CSS-Klassen und deren Übersetzungen hinzufügen und auswählen, wo diese Styles sichtbar sein sollen.

## 2. Theme Editor

Der Theme Editor ermöglicht ab Version 4 das direkte Bearbeiten von Theme-Assets aus dem Contao Backend.

### 2.1 Styles

Der Theme Editor ermöglicht das direkte Bearbeiten von SCSS-Dateien aus dem Contao Backend. Du kannst Original-Theme-Dateien überschreiben, indem du individuelle Varianten erstellst, Dateien umbenennen oder neue SCSS-Dateien anlegen.

Über eine Vergleichsansicht lassen sich Varianten und Original zeilenweise vergleichen und so Theme-Updates schneller nachvollziehen.

### 2.2 Webfonts

Im Tab „Webfonts" können Schriftarten für das Theme verwaltet werden. Es stehen zwei Wege zur Verfügung:

- **Google Fonts Katalog:** Schriften können direkt aus dem Google Webfont Katalog ausgewählt werden. Die Schriftdateien werden automatisch heruntergeladen und im Theme unter `layout/custom/fonts` abgelegt. Die zugehörige `@font-face`-Deklaration wird automatisch in `base/_fonts.scss` ergänzt.
- **Manueller Upload:** Alternativ können Schriftdateien (woff2, ttf, woff) auch manuell hochgeladen werden — z. B. für Schriften, die nicht bei Google Fonts verfügbar sind.

**Hinweis:** Nach dem Import oder Upload müssen die Schriftarten noch händisch in der `_variables.scss` eingetragen werden, damit sie im Theme verwendet werden. Nicht mehr benötigte Schriftarten lassen sich über den Button **„Ungenutzte @font-face bereinigen"** entfernen.

### 2.3 Bilder

Im Tab „Bilder" werden alle Bilddateien des Themes aus dem Verzeichnis `layout/<theme>/img` angezeigt. Erlaubt sind die Formate JPG, JPEG, PNG, GIF, SVG, WebP und AVIF bis zu einer Größe von 5 MB.

- **Ersetzen:** Bestehende Theme-Bilder lassen sich durch eigene Varianten überschreiben. Die neue Datei wird unter `layout/custom/img` abgelegt, das Original bleibt unberührt. In der Vorschau werden Custom und Original nebeneinander dargestellt.
- **Hinzufügen:** Neue Bilder und Unterordner können direkt im Custom-Bereich angelegt werden.
- **Umbenennen & Löschen:** Custom-Dateien können umbenannt oder gelöscht werden; beim Löschen einer Überschreibung wird automatisch wieder das Original verwendet.

### 2.4 JavaScript

Im Tab „JavaScript" lassen sich `.js`-Dateien aus `layout/<theme>/js` direkt im Backend bearbeiten — analog zum Styles-Editor mit Ace-Editor, Diff-Ansicht und Revert-Funktion.

- **Überschreiben:** Original-JS-Dateien des Themes können durch Custom-Versionen in `layout/custom/js` überschrieben werden.
- **Neu anlegen:** Eigene JS-Dateien und Unterordner können im Custom-Bereich angelegt werden.
- **Diff & Revert:** Unterschiede zum Original lassen sich zeilenweise anzeigen, Änderungen können auf das Original zurückgesetzt werden.
- **Umbenennen & Löschen:** Custom-Dateien lassen sich umbenennen oder löschen.

### Optionale Konfiguration

Theme und Anpassungen liegen standardmäßig unter `layout/theme` und `layout/custom`. Du kannst die Verzeichnisse für Layouts und Custom-SCSS aber über die `config/config.yaml` anpassen:

```yaml
theme_toolbox:
  layout_dir: 'layout' # Basis-Verzeichnis für Theme-Layouts
  custom_dir: 'layout/custom' # Verzeichnis für Custom-SCSS-Overrides
```

## 3. SCSS-Cache umgehen

Der SCSS-Compiler in Contao erkennt Änderungen in SCSS-Partials nicht, sodass der Cache nicht aktualisiert wird. Wenn du "Script-Cache umgehen" in den Contao-Wartungseinstellungen aktivierst, werden die SCSS-Dateien nicht zwischengespeichert, sondern bei jeden Aufruf gelöscht.

**Wichtig: Bitte stelle sicher, dass du das Umgehen des Script-Caches deaktivierst, nachdem du deine Arbeit an den SCSS-Dateien abgeschlossen hast, da das Deaktivieren des Script-Caches große Leistungsprobleme verursachen kann!**

## 4. Header- und Footer-Klassen

Im Seitenlayout lassen sich eigene Header- und Footer-Klassen im Seitenlayout vergeben und über Template-Anpassungen nutzen. Das `fe_page.html.twig` Template könnte folgendermaßen aussehen:

```twig
{% extends '@Contao/fe_page' %}

{% block header %}
  {% if header %}
    <header id="header" class="header {{ headerClass }}">
      <div class="inside">
        {{ header|raw }}
      </div>
    </header>
  {% endif %}
{% endblock %}

{% block footer %}
  {% if footer %}
    <footer id="footer" class="footer {{ footerClass }}">
      <div class="inside">
        {{ footer|raw }}
      </div>
    </footer>
  {% endif %}
{% endblock %}
```

## 5. Twig-Funktionen für Theme-Assets

Die Theme Toolbox stellt drei Twig-Funktionen bereit, um kompilierte CSS-, JavaScript- und Bild-Dateien in Twig-Templates einzubinden:

### `theme_css(entry)`

Gibt den Pfad zur kompilierten CSS-Datei zurück. Der Parameter `entry` entspricht dem Namen der Entry-Datei (Standard: `'default'`).

```twig
<link rel="stylesheet" href="{{ theme_css('default') }}">
{# Ausgabe: /assets/theme-name/css/default.css #}
```

Varianten wie `variant-1.scss` können dementsprechend über den Entry-Parameter `variant-1` ausgegeben werden.

### `theme_js(file)`

Gibt den Pfad zu einer JavaScript-Datei des Themes zurück.

```twig
<script src="{{ theme_js('main.js') }}"></script>
{# Ausgabe: /assets/theme-name/js/main.js #}
```

### `theme_img(file)`

Gibt den Pfad zu einer Bild-Datei des Themes zurück.

```twig
<img src="{{ theme_img('logo.svg') }}" alt="Logo">
{# Ausgabe: /assets/theme-name/img/logo.svg #}
```

## Development notes

### Code style (ECS)

```shell
# Prüfen
vendor/bin/ecs check

# Automatisch korrigieren
vendor/bin/ecs check --fix
```

### Code-Modernisierung (Rector)

```shell
# Vorschau der Änderungen
vendor/bin/rector --dry-run

# Änderungen anwenden
vendor/bin/rector
```

---

[1]: https://erdmann-freunde.de/produkte/contao-themes/
[2]: https://erdmann-freunde.de/
