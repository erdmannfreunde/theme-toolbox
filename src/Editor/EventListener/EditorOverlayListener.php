<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\Editor\EventListener;

use Contao\Config;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\LayoutModel;
use Contao\PageModel;
use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\TokenRegistry;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Frontend integration of the live editor: mounts the editor overlay (panel,
 * assets and inline registry) only when it should be visible — in public/demo
 * mode, or for a backend user when the editor is switched on in the system
 * maintenance. The persisted look itself lives in the compiled theme SCSS, so
 * nothing is injected for normal visitors.
 */
#[AsHook('generatePage')]
class EditorOverlayListener
{
    private const ASSET_BASE = 'bundles/erdmannfreundethemetoolbox';

    public function __construct(
        private readonly TokenRegistry $registry,
        private readonly TokenChecker $tokenChecker,
        private readonly Environment $twig,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly UrlGeneratorInterface $router,
        private readonly bool $publicMode = false,
    ) {
    }

    public function __invoke(PageModel $pageModel, LayoutModel $layout): void
    {
        $theme = $this->registry->getActiveTheme();

        if (null === $theme) {
            return;
        }

        // The persisted look comes from the compiled theme SCSS, so there is nothing to
        // inject for normal visitors — only mount the panel when it is visible: in the
        // public demo always, otherwise for a backend user *and* only when the editor has
        // been switched on in the system maintenance (Config key, off by default).
        $authenticated = $this->tokenChecker->hasBackendUser();

        if (!$this->publicMode && (!$authenticated || !Config::get('frontendThemeEditor'))) {
            return;
        }

        // editor.css is loaded inside the Shadow DOM by editor.js (not via TL_CSS), so
        // the host theme never sees it and cannot be affected by it.
        $GLOBALS['TL_JAVASCRIPT'][] = self::ASSET_BASE.'/contrast-guard.js|static';
        $GLOBALS['TL_JAVASCRIPT'][] = self::ASSET_BASE.'/editor.js|static';

        $data = [
            'mode' => $this->publicMode && !$authenticated ? 'public' : 'auth',
            'publicMode' => $this->publicMode,
            'canPersist' => $authenticated && !$this->publicMode,
            'theme' => $theme,
            'registry' => $this->registry->toArray($theme, $this->publicMode),
            'routes' => [
                'apply' => $this->router->generate('toolbox_editor_apply'),
                'fontDownload' => $this->router->generate('toolbox_editor_font_download'),
                'fontCatalog' => $this->router->generate('toolbox_editor_font_catalog'),
            ],
            'token' => $this->csrfTokenManager->getDefaultTokenValue(),
        ];

        $GLOBALS['TL_BODY'][] = $this->twig->render('@ErdmannFreundeThemeToolbox/frontend/toolbox_editor_panel.html.twig', [
            'editor_data' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'public_mode' => $this->publicMode,
            'can_persist' => $data['canPersist'],
        ]);
    }
}
