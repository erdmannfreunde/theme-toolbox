<?php

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;
use Contao\Input;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

final class RegisterFieldsInPaletteListener
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function onLoadContentCallback(DataContainer $dataContainer): void
    {
        if ('edit' !== Input::get('act')) {
            return;
        }

        $table = $dataContainer->table;

        $qb = $this->connection->createQueryBuilder()
            ->select('e.id', 'e.title', 'e.label', 'c.title as optgroup', 'c.classes', 'c.elements', 'c.fields')
            ->from('tl_toolbox_editor', 'e')
            ->innerJoin('e', 'tl_toolbox_editor_css', 'c', 'e.id = c.pid')
            ->addOrderBy('e.id')
            ->addOrderBy('c.sorting')
            ;

        if ('tl_content' === $table){
            $qb
                ->addSelect('c.elements AS allowedTypes')
                ->andWhere($qb->expr()->or('c.articles <> 1', 'c.elements'))
            ;
        }

        if ('tl_form_field' === $table){
            $qb
                ->addSelect('c.fields AS allowedTypes')
                ->andWhere($qb->expr()->or('c.articles <> 1', 'c.fields'))
            ;
        }

        if ('tl_article' === $table){
            $qb->andWhere('c.articles = 1');
        }

        $configs = $qb->execute()->fetchAll();

        if ('tl_article' !== $table) {
            $type = $this->connection
                ->createQueryBuilder()
                ->select('type')
                ->from($table)
                ->where('id = :id')
                ->setParameter('id', $dataContainer->id)
                ->execute()
                ->fetchColumn()
            ;
        }

        $options = [];

        $paletteManipulator = PaletteManipulator::create()
            ->addLegend('toolbox_legend', 'expert_legend', PaletteManipulator::POSITION_AFTER)
        ;

        foreach ($configs as $config) {
            $cssClasses = StringUtil::deserialize($config['classes'], true);

            if (($type = ($type ?? null))
                && ($allowedTypes = StringUtil::deserialize($config['allowedTypes'], true))
                && !in_array($type, $allowedTypes, true)) {
                continue;
            }

            $options[$config['id']][$config['optgroup']] =
                array_combine(array_column($cssClasses, 'key'), array_column($cssClasses, 'value'));
        }

        foreach ($configs as $config) {
            if (isset($GLOBALS['TL_DCA'][$table]['fields']['toolbox_css' . $config['id']])) {
                continue;
            }

            if (!isset($options[$config['id']])) {
                continue;
            }

            $GLOBALS['TL_DCA'][$table]['fields']['toolbox_css' . $config['id']] = [
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

            $paletteManipulator->addField('toolbox_css' . $config['id'], 'toolbox_legend', PaletteManipulator::POSITION_APPEND);
        }

        foreach ($GLOBALS['TL_DCA'][$table]['palettes'] as $k => $palette) {
            if ('__selector__' === $k) {
                continue;
            }

            $paletteManipulator->applyToPalette($k, $table);
        }
    }
}
