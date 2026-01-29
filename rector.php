<?php

declare(strict_types=1);

use Contao\Rector\Set\SetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/contao',
    ])
    ->withSets([
        SetList::CONTAO,
    ])
    ->withPhpSets(php81: true)
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
;
