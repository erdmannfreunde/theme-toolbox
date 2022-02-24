<?php

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener\DataContainer;

use Contao\StringUtil;
use Doctrine\DBAL\Connection;

final class SaveClasses
{
    private $connection;

    private static $classes = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function onSaveCallback($value, $dc)
    {
        $id = $dc->table . $dc->id;
        if (!isset(self::$classes[$id])) {
            self::$classes[$id] = [];
        }

        $mergedClasses = array_merge(self::$classes[$id], StringUtil::deserialize($value, true));
        if (count($mergedClasses) > count(array_unique($mergedClasses))) {
            throw new \Exception('CSS-Klassen doppelt vorhanden! Wird nicht gespeichert.');
        }

        self::$classes[$id] = $mergedClasses;

        $this->connection->update(
            $dc->table,
            ['toolbox_classes' => implode(' ', self::$classes[$id])],
            ['id' => $dc->id]
        );

        return null;
    }

    public function onLoadCallback($value, $dc)
    {
        $configs = $this->connection->createQueryBuilder()
            ->select('e.id', 'c.classes')
            ->from('tl_toolbox_editor', 'e')
            ->innerJoin('e', 'tl_toolbox_editor_css', 'c', 'e.id = c.pid')
            ->addOrderBy('e.id')
            ->addOrderBy('c.sorting')
            ->execute()
            ->fetchAll()
        ;

        $options = [];
        foreach ($configs as $config) {
            if ('toolbox_css' . $config['id'] !== $dc->field){
                continue;
            }

            $cssClasses = StringUtil::deserialize($config['classes'], true);

            $options[] = array_column($cssClasses, 'key');
        }

        $options = array_unique(array_merge(...$options));

        return array_values(array_filter($options, function ($option) use ($dc) {
            return 1 === preg_match(sprintf('/(^|\s)(%s)(\s|$)/', preg_quote($option)), $dc->activeRecord->toolbox_classes);
        }));
    }
}
