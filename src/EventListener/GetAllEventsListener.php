<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Module;
use Contao\StringUtil;

#[AsHook('getAllEvents')]
class GetAllEventsListener
{
    public function __invoke(array $events, array $calendars, int $timeStart, int $timeEnd, Module $module): array
    {
        foreach ($events as $k => $v) {
            foreach ($v as $kk => $vv) {
                foreach ($vv as $kkk => $event) {
                    if (!$event['toolbox_classes']) {
                        continue;
                    }

                    $events[$k][$kk][$kkk]['class'] .= ' '.$this->uniqueClasses($event['toolbox_classes']);
                }
            }
        }

        return $events;
    }

    private function uniqueClasses(string $classes): string
    {
        return implode(' ', array_unique(StringUtil::trimsplit(' ', $classes)));
    }
}
