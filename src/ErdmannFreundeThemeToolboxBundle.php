<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * The Bundle class.
 */
class ErdmannFreundeThemeToolboxBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
