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

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsCallback(table: 'tl_toolbox_editor_css', target: 'config.onload')]
class ToolboxEditorCssCallback
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function __invoke(DataContainer|null $dc = null): void
    {
        if (!$dc || !$dc->id || 'edit' !== $this->requestStack->getCurrentRequest()->query->get('act')) {
            return;
        }

        $GLOBALS['TL_LANG']['MSC']['ow_key'] = $GLOBALS['TL_LANG']['tl_toolbox_editor_css']['ow_key'];
        $GLOBALS['TL_LANG']['MSC']['ow_value'] = $GLOBALS['TL_LANG']['tl_toolbox_editor_css']['ow_value'];
    }
}
