<?php

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\EventListener\DataContainer;

use Contao\DataContainer;
use Symfony\Component\HttpFoundation\RequestStack;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;

#[AsCallback(table: 'tl_toolbox_editor_css', target: 'config.onload')]
class ToolboxEditorCssCallback
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function __invoke(DataContainer $dc = null): void
    {
        if (null === $dc || !$dc->id || 'edit' !== $this->requestStack->getCurrentRequest()->query->get('act')) {
            return;
        }

        $GLOBALS['TL_LANG']['MSC']['ow_key'] = $GLOBALS['TL_LANG']['tl_toolbox_editor_css']['ow_key'];
        $GLOBALS['TL_LANG']['MSC']['ow_value'] = $GLOBALS['TL_LANG']['tl_toolbox_editor_css']['ow_value'];
    }
}