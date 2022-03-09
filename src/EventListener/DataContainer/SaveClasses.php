<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener\DataContainer;

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
        $id = $dc->table.$dc->id;

        if (!isset(self::$classes[$id])) {
            self::$classes[$id] = [];
        }

        $mergedClasses = array_merge(self::$classes[$id], StringUtil::deserialize($value, true));

        if (\count($mergedClasses) > \count(array_unique($mergedClasses))) {
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

    public function onLoadCallback($value, $dc): array
    {
        $configs = $this->connection->createQueryBuilder()
            ->select('e.id', 'c.classes')
            ->from('tl_toolbox_editor', 'e')
            ->innerJoin('e', 'tl_toolbox_editor_css', 'c', 'e.id = c.pid')
            ->addOrderBy('e.id')
            ->addOrderBy('c.sorting')
            ->executeQuery()
            ->fetchAllAssociative()
        ;

        $options = [];

        foreach ($configs as $config) {
            if ('toolbox_css'.$config['id'] !== $dc->field) {
                continue;
            }

            $cssClasses = StringUtil::deserialize($config['classes'], true);

            $options[] = array_column($cssClasses, 'key');
        }

        $options = array_unique(array_merge(...$options));

        return array_values(array_filter($options, static fn ($option) => 1 === preg_match(sprintf('/(^|\s)(%s)(\s|$)/', preg_quote($option)), $dc->activeRecord->toolbox_classes ?? '')));
    }
}
