[![Build Status](https://travis-ci.org/erdmannfreunde/theme-toolbox.svg)](https://travis-ci.org/erdmannfreunde/theme-toolbox)
[![Latest Version tagged](http://img.shields.io/github/tag/erdmannfreunde/theme-toolbox.svg)](https://github.com/erdmannfreunde/theme-toolbox/tags)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/erdmannfreunde/theme-toolbox.svg)](https://packagist.org/packages/https://github.com/erdmannfreunde/theme-toolbox/tags)
[![Installations via composer per month](https://img.shields.io/packagist/dm/erdmannfreunde/theme-toolbox.svg)](https://packagist.org/packages/erdmannfreunde/theme-toolbox)

# Theme Toolbox

This package holds helpful tools to work with the [Contao Themes][1] by [Erdmann & Freunde][2].

## 1. CSS Class Picker

If you don't want to give your clients a list of class names, that can be used for variants and specific styles, 
you can use the theme toolbox to add human readable styles to elements, modules and articles. In toolbox editor you
can add css classes and it's translations and chose where this styles will be visible. 

## 2. Bypass SCSS cache 

While we encourage you to do frontend theme development on your local machine (there are so many advantages!), our 
themes will come with a "server edition" that allows you to let Contao compile your SCSS-files on your server.

When enabling "Bypass script cache" in the Contao Maintenance settings, the SCSS files do not get cached in production
mode.

CAUTION:
-----------------
**Please make sure to disable bypassing script cache after you finished your work on SCSS-files, as disabling the script cache can cause big performance issues!**

## Development notes:

Code style:

```shell
vendor/bin/ecs check src contao --fix
```

-------

[1]: https://erdmann-freunde.de/produkte/contao-themes/
[2]: https://erdmann-freunde.de/
