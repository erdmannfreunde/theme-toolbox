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

        // Check if this is a backend request with our module
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/contao') || $request->query->get('do') !== 'themeScssEditor') {
            return;
        }

        // Redirect to our controller route (whitelist allowed parameters)
        $params = array_intersect_key($request->query->all(), array_flip(['theme', 'file']));

        $url = $this->urlGenerator->generate('theme_scss_editor_index', $params);

        $event->setResponse(new RedirectResponse($url));
    }
}
