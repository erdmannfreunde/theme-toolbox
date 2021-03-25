<?php

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

final class SaveClasses
{
    private Connection $connection;

    private static array $classes = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function onSaveCallback($value, $dc)
    {
        if (!isset(self::$classes[$dc->table . $dc->id])) {
            self::$classes[$dc->table . $dc->id] = explode(' ', $dc->activeRecord->toolbox_classes);
        }

        self::$classes[$dc->table . $dc->id] =
            array_unique(array_merge(self::$classes[$dc->table . $dc->id], StringUtil::deserialize($value, true)));

        $this->connection->update(
            'tl_content',
            ['toolbox_classes' => implode(' ', self::$classes[$dc->table . $dc->id])],
            ['id' => $dc->id]
        );

        return null;
    }

    public function onLoadCallback($value, $dc)
    {
        $configs = $this->connection->createQueryBuilder()
            ->select('e.id', 'c.classes', 'c.elements')
            ->from('tl_toolbox_editor', 'e')
            ->innerJoin('e', 'tl_toolbox_editor_css', 'c', 'e.id=c.pid')
            ->addOrderBy('e.id')
            ->addOrderBy('c.sorting')
            ->execute()
            ->fetchAll()
        ;

        foreach ($configs as $config) {
            if ('toolbox_css' . $config['id'] !== $dc->field){
                continue;
            }

            $cssClasses = StringUtil::deserialize($config['classes'], true);

            $options[] = array_column($cssClasses, 'key');
        }

        $options = array_unique(array_merge(...$options));

        return array_intersect(explode(' ', $dc->activeRecord->toolbox_classes), $options);
    }
}