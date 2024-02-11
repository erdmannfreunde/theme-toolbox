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

use Composer\InstalledVersions;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\Database;
use Contao\DataContainer;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;

final class RegisterFieldsInPaletteListener
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @Callback(table="tl_article", target="config.onload", priority=-10)
     * @Callback(table="tl_content", target="config.onload", priority=-10)
     * @Callback(table="tl_form_field", target="config.onload", priority=-10)
     * @Callback(table="tl_news", target="config.onload", priority=-10)
     * @Callback(table="tl_calendar_events", target="config.onload", priority=-10)
     * @Callback(table="tl_faq", target="config.onload", priority=-10)
     * @Callback(table="tl_module", target="config.onload", priority=-10)
     */
    public function onLoadContentCallback(DataContainer $dataContainer): void
    {
        if (!\in_array(Input::get('act'), ['edit', 'editAll', 'overrideAll'], true)) {
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

        if ('tl_content' === $table) {
            $qb->andWhere('c.use_ce = 1');
            $qb->addSelect('c.elements AS allowedTypes');
        }

        if ('tl_form_field' === $table) {
            $qb->andWhere('c.use_ffl = 1');
            $qb->addSelect('c.fields AS allowedTypes');
        }

        if ('tl_article' === $table) {
            $qb->andWhere('c.articles = 1');
        }

        if ('tl_news' === $table && InstalledVersions::isInstalled('contao/news-bundle')) {
            if (Database::getInstance()->fieldExists('news', 'tl_toolbox_editor_css')) {
                $qb->andWhere('c.news = 1');
            } else {
                return;
            }
        }

        if ('tl_calendar_events' === $table && InstalledVersions::isInstalled('contao/calendar-bundle')) {
            if (Database::getInstance()->fieldExists('events', 'tl_toolbox_editor_css')) {
                $qb->andWhere('c.events = 1');
            } else {
                return;
            }
        }

        if ('tl_faq' === $table && InstalledVersions::isInstalled('contao/faq-bundle')) {
            if (Database::getInstance()->fieldExists('faqs', 'tl_toolbox_editor_css')) {
                $qb->andWhere('c.faqs = 1');
            } else {
                return;
            }
        }

        if ('tl_module' === $table) {
            if (InstalledVersions::isInstalled('contao/calendar-bundle')) {
                if (Database::getInstance()->fieldExists('events', 'tl_toolbox_editor_css')) {
                    $qb->andWhere('c.events = 1');
                } else {
                    return;
                }
            }

            if (InstalledVersions::isInstalled('contao/news-bundle')) {
                if (Database::getInstance()->fieldExists('news', 'tl_toolbox_editor_css')) {
                    $qb->andWhere('c.news = 1');
                } else {
                    return;
                }
            }

            if (InstalledVersions::isInstalled('contao/faq-bundle')) {
                if (Database::getInstance()->fieldExists('faqs', 'tl_toolbox_editor_css')) {
                    $qb->andWhere('c.faqs = 1');
                } else {
                    return;
                }
            }
        }

        $configs = $qb->executeQuery()->fetchAllAssociative();

        if ('tl_article' !== $table &&
            'tl_news' !== $table &&
            'tl_calendar_events' !== $table &&
            'tl_faq' !== $table &&
            'tl_module' !== $table
        ) {
            $type = $this->connection
                ->createQueryBuilder()
                ->select('type')
                ->from($table)
                ->where('id = :id')
                ->setParameter('id', $dataContainer->id)
                ->executeQuery()
                ->fetchOne()
            ;
        }

        $options = [];

        $paletteManipulator = PaletteManipulator::create()
            ->addLegend('toolbox_legend', 'expert_legend', PaletteManipulator::POSITION_AFTER)
        ;

        foreach ($configs as $config) {
            $cssClasses = StringUtil::deserialize($config['classes'], true);

            if (
                ($type = ($type ?? null))
                && ($allowedTypes = StringUtil::deserialize($config['allowedTypes'], true))
                && !\in_array($type, $allowedTypes, true)
            ) {
                continue;
            }

            $options[$config['id']][$config['optgroup']] =
                array_combine(array_column($cssClasses, 'key'), array_column($cssClasses, 'value'));
        }

        $permission = $this->checkPermission($table);

        foreach ($configs as $config) {
            if (isset($GLOBALS['TL_DCA'][$table]['fields']['toolbox_css'.$config['id']])) {
                continue;
            }

            if (!isset($options[$config['id']])) {
                continue;
            }

            $GLOBALS['TL_DCA'][$table]['fields']['toolbox_css'.$config['id']] = [
                'label' => [$config['label'] ?: $config['title'], 'Sie können CSS-Klassen für die Kategorie auswählen.'],
                'exclude' => $permission,
                'search' => true,
                'inputType' => 'select',
                'options' => $options[$config['id']],
                'load_callback' => [[SaveClasses::class, 'onLoadCallback']],
                'save_callback' => [[SaveClasses::class, 'onSaveCallback']],
                'eval' => [
                    'mandatory' => false,
                    'multiple' => true,
                    'size' => 10,
                    'doNotSaveEmpty' => true,
                    'tl_class' => 'w50 w50h autoheight',
                    'chosen' => true,
                ],
            ];

            $paletteManipulator->addField('toolbox_css'.$config['id'], 'toolbox_legend', PaletteManipulator::POSITION_APPEND);
        }

        foreach (array_keys($GLOBALS['TL_DCA'][$table]['palettes']) as $k) {
            if ('__selector__' === $k) {
                continue;
            }

            $paletteManipulator->applyToPalette($k, $table);
        }
    }

    public function checkPermission(string $table): bool
    {
        if (System::getContainer()->get('security.helper')
            ->isGranted(ContaoCorePermissions::USER_CAN_EDIT_FIELD_OF_TABLE, $table . '::' . 'toolbox_permissions')) {
            return false;
        }

        return true;
    }
}
