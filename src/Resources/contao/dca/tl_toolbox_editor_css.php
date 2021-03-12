<?php

/**
 * Table tl_toolbox_editor_css
 */
$GLOBALS['TL_DCA']['tl_toolbox_editor_css'] = array(
    // Config
    'config'   => array(
        'dataContainer'               => 'Table',
        'ptable'                      => 'tl_toolbox_editor',
        'switchToEdit'                => true,
        'enableVersioning'            => true,
        'sql' => array(
            'keys' => array(
                'id' => 'primary',
                'pid' => 'index'
            )
        )
    ),
    // List
    'list' => array(
        'sorting' => array(
            'mode'                    => 4,
            'fields'                  => array('sorting'),
            'panelLayout'             => 'filter;sort,search,limit',
            'headerFields'            => ['title'],
            'child_record_callback'   => ['tl_toolbox_editor_css', 'listItems'],
        ),
        'label' => array(
            'fields'                  => array('title'),
            'format'                  => '%s'
        ),
        'global_operations' => array(
            'all' => array(
                'label'               => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'                => 'act=select',
                'class'               => 'header_edit_all',
                'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            )
        ),
        'operations' => array(
            'edit' => array(
                'label'               => &$GLOBALS['TL_LANG']['tl_toolbox_editor_css']['edit'],
                'href'                => 'act=edit',
                'icon'                => 'edit.gif'
            ),
            'copy'   => array(
                'label'               => &$GLOBALS['TL_LANG']['tl_toolbox_editor_css']['copy'],
                'href'                => 'act=copy',
                'icon'                => 'copy.gif',
            ),
            'delete' => array(
                'label'               => &$GLOBALS['TL_LANG']['tl_toolbox_editor_css']['delete'],
                'href'                => 'act=delete',
                'icon'                => 'delete.gif',
                'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
            ),
            'show' => array(
                'label'               => &$GLOBALS['TL_LANG']['tl_toolbox_editor_css']['show'],
                'href'                => 'act=show',
                'icon'                => 'show.gif'
            )
        )
    ),
    // Palettes
    'palettes' => array(
        'default' => '{title_legend},title,classes;{elements_legend},elements;{fields_legend},fields;{modules_legend},modules;'
    ),
    // Fields
    'fields'   => array(
        'id' => array(
            'sql'                     => "int(10) unsigned NOT NULL auto_increment"
        ),
        'pid' => array(
            'foreignKey'              => 'tl_toolbox_editor.title',
            'sql'                     => "int(10) unsigned NOT NULL default 0",
            'relation'                => array('type'=>'belongsTo', 'load'=>'lazy')
        ),
        'tstamp' => array(
            'sql'                     => "int(10) unsigned NOT NULL default '0'"
        ),
        'sorting'       => array(
            'sql'                     => "int(10) unsigned NOT NULL default '0'",
        ),
        'title' => array(
            'label'                   => &$GLOBALS['TL_LANG']['tl_toolbox_editor_css']['title'],
            'exclude'                 => true,
            'search'                  => true,
            'inputType'               => 'text',
            'eval'                    => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'classes' => array(
            'label'                   => &$GLOBALS['TL_LANG']['tl_toolbox_editor_css']['class'],
            'exclude'                 => true,
            'search'                  => true,
            'inputType'               => 'keyValueWizard',
            'eval'                    => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w100 clr'),
            'sql'                     => "text NULL"
        ),
        'elements' => array(
            'label'                   => &$GLOBALS['TL_LANG']['tl_toolbox_editor_css']['elements'],
            'exclude'                 => true,
            'filter'                  => true,
            'inputType'               => 'checkbox',
            'options_callback'        => array('tl_toolbox_editor_css', 'getContentElements'),
            'reference'               => &$GLOBALS['TL_LANG']['CTE'],
            'eval'                    => array('multiple'=>true, 'helpwizard'=>true),
            'sql'                     => "blob NULL"
        ),
        'fields' => array(
            'label'                   => &$GLOBALS['TL_LANG']['tl_toolbox_editor_css']['fields'],
            'exclude'                 => true,
            'filter'                  => true,
            'inputType'               => 'checkbox',
            'options'                 => array_keys($GLOBALS['TL_FFL']),
            'reference'               => &$GLOBALS['TL_LANG']['FFL'],
            'eval'                    => array('multiple'=>true, 'helpwizard'=>true),
            'sql'                     => "blob NULL"
        ),
        'modules' => array(
            'label'                   => &$GLOBALS['TL_LANG']['tl_toolbox_editor_css']['modules'],
            'exclude'                 => true,
            'filter'                  => true,
            'inputType'               => 'checkbox',
            'options_callback'        => array('tl_toolbox_editor_css', 'getModules'),
            'reference'               => &$GLOBALS['TL_LANG']['MOD'],
            'eval'                    => array('multiple'=>true, 'helpwizard'=>true),
            'sql'                     => "blob NULL"
        ),
    )
);

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 */
class tl_toolbox_editor_css extends Backend
{

    /**
     * Return all content elements
     *
     * @return array
     */
    public function getContentElements()
    {
        return array_map('array_keys', $GLOBALS['TL_CTE']);
    }

    /**
     * Return all modules except profile modules
     *
     * @param Contao\DataContainer $dc
     *
     * @return array
     */
    public function getModules(Contao\DataContainer $dc)
    {
        $arrModules = array();

        foreach ($GLOBALS['BE_MOD'] as $k=>$v) {
            if (empty($v)) {
                continue;
            }

            foreach ($v as $kk=>$vv) {
                if (isset($vv['disablePermissionChecks']) && $vv['disablePermissionChecks'] === true) {
                    unset($v[$kk]);
                }
            }

            $arrModules[$k] = array_keys($v);
        }

        return $arrModules['content'];
    }

    /**
     * Add the type of input field.
     *
     * @param array $arrRow
     *
     * @return string
     */
    public function listItems($arrRow): string
    {
        $classes = array();

        if (!empty($arrRow['classes']) && \is_array(($tmp = StringUtil::deserialize($arrRow['classes'])))) {
            foreach ($tmp as $v) {
                $classes[] = $v['value'];
            }
        }

        return '<div class="tl_content_left">' .$arrRow['title'] .' <span style="color:#999;padding-left:3px">[' . implode(', ', $classes) .']</span></div>';
    }
}
