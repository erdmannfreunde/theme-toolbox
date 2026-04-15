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

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: 'kernel.request', priority: 1000)]
class BackendModuleRedirectListener
{
    private const MODULE_ROUTES = [
        'themeFileEditor' => [
            'route' => 'theme_file_editor_index',
            'params' => ['theme', 'file', 'tab'],
        ],
        'themeUpdate' => [
            'route' => 'theme_update_index',
            'params' => [],
        ],
    ];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only handle main requests
        if (!$event->isMainRequest()) {
            return;
        }

        // Check if this is a backend request with one of our modules
        $path = $request->getPathInfo();
        $module = $request->query->get('do', '');

        if (!str_starts_with($path, '/contao') || !isset(self::MODULE_ROUTES[$module])) {
            return;
        }

        $config = self::MODULE_ROUTES[$module];

        // Redirect to our controller route (whitelist allowed parameters)
        $params = $config['params'] ? array_intersect_key($request->query->all(), array_flip($config['params'])) : [];

        $url = $this->urlGenerator->generate($config['route'], $params);

        $event->setResponse(new RedirectResponse($url));
    }
}
