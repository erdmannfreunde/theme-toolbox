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
use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\PresetApplier;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Persist (apply) for the live editor. Backend-scoped and refused in public mode —
 * the public/demo editor never writes server-side (§5.1, G8).
 */
#[Route('/contao/themeToolbox/editor', defaults: ['_scope' => 'backend', '_token_check' => true])]
class PersistController extends AbstractBackendController
{
    private const TRANSLATION_DOMAIN = 'contao_toolbox_editor';

    public function __construct(
        private readonly PresetApplier $presetApplier,
        private readonly ThemeScssFileManager $fileManager,
        private readonly TranslatorInterface $translator,
        private readonly bool $publicMode = false,
    ) {
    }

    #[Route('/apply', name: 'toolbox_editor_apply', methods: ['POST'])]
    public function apply(Request $request): JsonResponse
    {
        $this->denyInPublicMode();

        $theme = (string) $request->request->get('theme', '');
        $preset = $this->decodePreset($request->request->get('preset'));

        if (!$this->isValidTheme($theme)) {
            return new JsonResponse(['ok' => false, 'error' => 'Invalid theme'], 400);
        }

        if (null === $preset) {
            return new JsonResponse(['ok' => false, 'error' => 'Invalid preset'], 400);
        }

        $result = $this->presetApplier->apply($theme, $preset);

        return new JsonResponse([
            'ok' => true,
            'corrected' => $result['corrected'],
            'values' => $result['values'],
            'message' => $result['corrected']
                ? $this->translator->trans('error.invalid_preset', [], self::TRANSLATION_DOMAIN)
                : $this->translator->trans('applied', [], self::TRANSLATION_DOMAIN),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePreset(mixed $raw): array|null
    {
        if (\is_array($raw)) {
            return $raw;
        }

        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : null;
    }

    private function isValidTheme(string $theme): bool
    {
        return '' !== $theme && isset($this->fileManager->getAvailableThemes()[$theme]);
    }

    private function denyInPublicMode(): void
    {
        if ($this->publicMode) {
            throw new AccessDeniedHttpException('Persisting is disabled in public mode.');
        }
    }
}
