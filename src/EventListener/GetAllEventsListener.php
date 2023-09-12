<?php

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener;

use Contao\Module;
use Contao\StringUtil;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;

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
