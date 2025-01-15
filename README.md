[![Build Status](https://travis-ci.org/erdmannfreunde/theme-toolbox.svg)](https://travis-ci.org/erdmannfreunde/theme-toolbox)
[![Latest Version tagged](http://img.shields.io/github/tag/erdmannfreunde/theme-toolbox.svg)](https://github.com/erdmannfreunde/theme-toolbox/tags)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/erdmannfreunde/theme-toolbox.svg)](https://packagist.org/packages/https://github.com/erdmannfreunde/theme-toolbox/tags)
[![Installations via composer per month](https://img.shields.io/packagist/dm/erdmannfreunde/theme-toolbox.svg)](https://packagist.org/packages/erdmannfreunde/theme-toolbox)

# Theme Toolbox

Dieses Paket enthält hilfreiche Tools zur Arbeit mit den [Contao Themes][1] von [Erdmann & Freunde][2].

## 1. CSS-Klassen-Auswahl

Wenn du deinen Kunden keine Liste von Klassennamen für Varianten und spezifische Stile geben möchtest, kannst du die Theme-Toolbox verwenden, um menschenlesbare Stile zu Elementen, Modulen und Artikeln hinzuzufügen. Im Toolbox-Editor kannst du CSS-Klassen und deren Übersetzungen hinzufügen und auswählen, wo diese Styles sichtbar sein sollen.

## 2. SCSS-Cache umgehen

Der SCSS-Compiler in Contao erkennt Änderungen in SCSS-Partials nicht, sodass der Cache nicht aktualisiert wird. Wenn du "Script-Cache umgehen" in den Contao-Wartungseinstellungen aktivierst, werden die SCSS-Dateien nicht zwischengespeichert, sondern bei jeden Aufruf gelöscht.

**Wichtig: Bitte stelle sicher, dass du das Umgehen des Script-Caches deaktivierst, nachdem du deine Arbeit an den SCSS-Dateien abgeschlossen hast, da das Deaktivieren des Script-Caches große Leistungsprobleme verursachen kann!**

## 3. Header- und Footer-Klassen

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

## Development notes:

Code style:

```shell
vendor/bin/ecs check src contao --fix
```

---

[1]: https://erdmann-freunde.de/produkte/contao-themes/
[2]: https://erdmann-freunde.de/
