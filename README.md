[![Build Status](https://travis-ci.org/erdmannfreunde/theme-toolbox.svg)](https://travis-ci.org/erdmannfreunde/theme-toolbox)
[![Latest Version tagged](http://img.shields.io/github/tag/erdmannfreunde/theme-toolbox.svg)](https://github.com/erdmannfreunde/theme-toolbox/tags)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/erdmannfreunde/theme-toolbox.svg)](https://packagist.org/packages/https://github.com/erdmannfreunde/theme-toolbox/tags)
[![Installations via composer per month](https://img.shields.io/packagist/dm/erdmannfreunde/theme-toolbox.svg)](https://packagist.org/packages/erdmannfreunde/theme-toolbox)

# Theme Toolbox

Dieses Paket enthält hilfreiche Tools zur Arbeit mit den [Contao Themes][1] von [Erdmann & Freunde][2].

## 1. CSS-Klassen-Auswahl

Wenn du deinen Kunden keine Liste von Klassennamen für Varianten und spezifische Stile geben möchtest, kannst du die Theme-Toolbox verwenden, um menschenlesbare Stile zu Elementen, Modulen und Artikeln hinzuzufügen. Im Toolbox-Editor kannst du CSS-Klassen und deren Übersetzungen hinzufügen und auswählen, wo diese Styles sichtbar sein sollen.

## 2. Theme Editor

Der Theme Editor ermöglicht das direkte Bearbeiten von SCSS-Dateien aus dem Contao Backend. Du kannst Original-Theme-Dateien überschreiben, indem du individuelle Versionen erstellst, umbenennen oder neue SCSS-Dateien anlegen.

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
