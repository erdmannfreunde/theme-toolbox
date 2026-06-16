<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\Editor\Controller;

use Contao\CoreBundle\Controller\AbstractBackendController;
use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\GoogleFontBridge;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Google-font search + self-host download for the live editor. Backend-scoped:
 * the Contao firewall already restricts these routes to authenticated backend
 * users, so they are unreachable for anonymous public-mode visitors. In addition
 * the routes refuse to run when the editor is in public mode (§5.1).
 */
#[Route('/contao/themeToolbox/editor', defaults: ['_scope' => 'backend', '_token_check' => true])]
class FontController extends AbstractBackendController
{
    private const TRANSLATION_DOMAIN = 'contao_toolbox_editor';

    public function __construct(
        private readonly GoogleFontBridge $fontBridge,
        private readonly ThemeScssFileManager $fileManager,
        private readonly TranslatorInterface $translator,
        private readonly bool $publicMode = false,
    ) {
    }

    #[Route('/font/catalog', name: 'toolbox_editor_font_catalog', defaults: ['_token_check' => false], methods: ['GET'])]
    public function catalog(Request $request): JsonResponse
    {
        $this->denyInPublicMode();

        $search = trim((string) $request->query->get('search', ''));
        $category = trim((string) $request->query->get('category', ''));
        $limit = max(1, min(50, (int) $request->query->get('limit', 25)));

        try {
            $fonts = $this->fontBridge->searchCatalog($search, $category, $limit);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 502);
        }

        return new JsonResponse(['ok' => true, 'fonts' => $fonts]);
    }

    #[Route('/font/download', name: 'toolbox_editor_font_download', methods: ['POST'])]
    public function download(Request $request): JsonResponse
    {
        $this->denyInPublicMode();

        $theme = (string) $request->request->get('theme', '');
        $family = trim((string) $request->request->get('family', ''));

        if (!$this->isValidTheme($theme) || '' === $family) {
            return new JsonResponse(['ok' => false, 'error' => 'Missing parameters'], 400);
        }

        try {
            $result = $this->fontBridge->import($theme, $family);
        } catch (\RuntimeException $e) {
            return new JsonResponse(
                [
                    'ok' => false,
                    'error' => $this->translator->trans('error.font_failed', [], self::TRANSLATION_DOMAIN),
                ],
                502,
            );
        }

        $assetBase = $request->getSchemeAndHttpHost().$request->getBasePath().'/assets/'.$theme.'/';

        $faces = array_map(
            static fn (array $face): array => [
                'weight' => $face['weight'],
                'url' => $assetBase.ltrim($face['path'], '/'),
            ],
            $result['faces'],
        );

        return new JsonResponse([
            'ok' => true,
            'family' => $result['family'],
            'value' => $result['value'],
            'weights' => $result['weights'],
            'faces' => $faces,
        ]);
    }

    private function isValidTheme(string $theme): bool
    {
        return '' !== $theme && isset($this->fileManager->getAvailableThemes()[$theme]);
    }

    private function denyInPublicMode(): void
    {
        if ($this->publicMode) {
            throw new AccessDeniedHttpException('Font download is disabled in public mode.');
        }
    }
}
