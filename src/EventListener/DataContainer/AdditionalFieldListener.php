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
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\Database;
use Contao\DataContainer;
use Contao\Input;
use Doctrine\DBAL\Connection;

final class AdditionalFieldListener
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @Callback(table="tl_toolbox_editor_css", target="config.onload", priority=-10)
     */
    public function onLoadContentCallback(DataContainer $dataContainer): void
    {
        if (!\in_array(Input::get('act'), ['edit', 'editAll'], true)) {
            return;
        }

        $table = $dataContainer->table;

        if (InstalledVersions::isInstalled('contao/news-bundle')) {
            PaletteManipulator::create()
                ->addField('news', 'scope_legend', PaletteManipulator::POSITION_APPEND)
                ->applyToPalette('default', $table)
            ;
        }

        if (InstalledVersions::isInstalled('contao/calendar-bundle')) {
            PaletteManipulator::create()
                ->addField('events', 'scope_legend', PaletteManipulator::POSITION_APPEND)
                ->applyToPalette('default', $table)
            ;
        }

        if (InstalledVersions::isInstalled('contao/faq-bundle')) {
            PaletteManipulator::create()
                ->addField('faqs', 'scope_legend', PaletteManipulator::POSITION_APPEND)
                ->applyToPalette('default', $table)
            ;
        }
    }
}