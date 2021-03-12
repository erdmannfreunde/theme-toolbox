<?php

use ErdmannFreunde\ThemeToolboxBundle\EventListener\DataContainer\RegisterFieldsInPaletteListener;

$GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][]   = [RegisterFieldsInPaletteListener::class, 'onLoadContentCallback'];

$GLOBALS['TL_DCA']['tl_content']['fields']['toolbox_classes'] = [
    'label'            => &$GLOBALS['TL_LANG']['tl_content']['toolbox_classes'],
    'exclude'          => true,
    'search'           => true,
    'inputType'        => 'select',
    'options' => [
        'Außenabstand' => ['m-t-1' => 'Abstand oben 1', 'm-t-2' => 'Abstand oben 2'],
        'Innenabstand' => ['p-t-1' => 'Abstand oben 1', 'p-t-2' => 'Abstand oben 2']
    ],
    'eval'             => [
        'mandatory' => false,
        'multiple'  => true,
        'size'      => 10,
        'tl_class'  => 'w50 w50h autoheight',
        'chosen'    => true,
    ],
    'sql'              => 'text NULL',
];
