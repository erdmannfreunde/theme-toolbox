<?php

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;
use Contao\Input;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

final class RegisterFieldsInPaletteListener
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function onLoadContentCallback(DataContainer $dataContainer): void
    {
        if ('edit' !== Input::get('act')) {
            return;
        }

        $configs = $this->connection->createQueryBuilder()
            ->select('e.id', 'e.title', 'e.label', 'c.title as optgroup', 'c.classes', 'c.elements')
            ->from('tl_toolbox_editor', 'e')
            ->innerJoin('e', 'tl_toolbox_editor_css', 'c', 'e.id=c.pid')
            ->addOrderBy('e.id')
            ->addOrderBy('c.sorting')
            ->execute()
            ->fetchAll()
        ;

        $type = $this->connection
            ->executeQuery('SELECT `type` FROM tl_content WHERE id=:id', ['id' => $dataContainer->id])
            ->fetchColumn()
        ;

        $options = [];

        $paletteManipulator = PaletteManipulator::create()
            ->addLegend('toolbox_legend', 'expert_legend', PaletteManipulator::POSITION_BEFORE)
        ;

        foreach ($configs as $config) {
            $cssClasses = StringUtil::deserialize($config['classes'], true);
            $elements = StringUtil::deserialize($config['elements'], true);

            if (!in_array($type, $elements, true)) {
                continue;
            }

            $options[$config['id']][$config['optgroup']] =
                array_combine(array_column($cssClasses, 'key'), array_column($cssClasses, 'value'));
        }

        foreach ($configs as $config) {
            if (isset($GLOBALS['TL_DCA']['tl_content']['fields']['toolbox_css' . $config['id']])) {
                continue;
            }

            if (!isset($options[$config['id']])) {
                continue;
            }

            $GLOBALS['TL_DCA']['tl_content']['fields']['toolbox_css' . $config['id']] = [
                'label'         => [$config['label'] ?: $config['title'], 'Sie können CSS-Klassen für die Kategorie auswählen.'],
                'search'        => true,
                'inputType'     => 'select',
                'options'       => $options[$config['id']],
                'load_callback' => [[SaveClasses::class, 'onLoadCallback']],
                'save_callback' => [[SaveClasses::class, 'onSaveCallback']],
                'eval'          => [
                    'mandatory' => false,
                    'multiple'       => true,
                    'size'           => 10,
                    'doNotSaveEmpty' => true,
                    'tl_class'       => 'w50 w50h autoheight',
                    'chosen'         => true,
                ],
            ];

            $paletteManipulator->addField(
                'toolbox_css' . $config['id'],
                'toolbox_legend',
                PaletteManipulator::POSITION_APPEND
            );
        }

        foreach ($GLOBALS['TL_DCA']['tl_content']['palettes'] as $k => $palette) {
            if ('__selector__' === $k || false === strpos($palette, 'cssID')) {
                continue;
            }

            $paletteManipulator->applyToPalette($k, 'tl_content');
        }
    }
}