<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_toolbox_editor'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'ctable' => ['tl_toolbox_editor_css'],
        'switchToEdit' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => 1,
            'fields' => ['title'],
            'flag' => 1,
            'panelLayout' => 'search,limit',
        ],
        'label' => [
            'fields' => ['title'],
            'format' => '%s',
        ],
        'global_operations' => [
            'all' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations' => [
            'edit' => [
                'label' => &$GLOBALS['TL_LANG']['tl_toolbox_editor']['editheader'],
                'href' => 'act=edit',
                'icon' => 'header.svg',
            ],
            'children' => [
                'href' => 'table=tl_toolbox_editor_css',
                'icon' => 'children.svg',
            ],
            'copy' => [
                'label' => &$GLOBALS['TL_LANG']['tl_toolbox_editor']['copy'],
                'href' => 'act=copy',
                'icon' => 'copy.svg',
            ],
            'delete' => [
                'label' => &$GLOBALS['TL_LANG']['tl_toolbox_editor']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => sprintf(
                    "onclick=\"if(!confirm('%s'))return false;Backend.getScrollOffset()\"",
                    ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null)
                ),
            ],
            'show' => [
                'label' => &$GLOBALS['TL_LANG']['tl_toolbox_editor']['show'],
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],
    'palettes' => [
        'default' => '{title_legend},title,label',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title' => [
            'label' => &$GLOBALS['TL_LANG']['tl_toolbox_editor']['title'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'label' => [
            'label' => &$GLOBALS['TL_LANG']['tl_toolbox_editor']['label'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
    ],
];
