<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\Controller\Backend;

use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\System;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeUpdateService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/contao/themeUpdate', defaults: ['_scope' => 'backend', '_token_check' => true])]
class ThemeUpdateController extends AbstractBackendController
{
    private const TRANSLATION_DOMAIN = 'contao_tl_theme_update';

    public function __construct(
        private readonly ThemeUpdateService $updateService,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'theme_update_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->initializeContaoFramework();

        System::loadLanguageFile('default');
        System::loadLanguageFile('modules');
        System::loadLanguageFile('tl_theme_update');

        $session = $request->getSession();

        $error = null;
        $result = null;

        if ($session instanceof FlashBagAwareSessionInterface) {
            $error = $session->getFlashBag()->get('theme_update_error')[0] ?? null;
            $result = $session->getFlashBag()->get('theme_update_result')[0] ?? null;
        }

        return $this->render('@ErdmannFreundeThemeToolbox/backend/theme_update/index.html.twig', [
            'headline' => $GLOBALS['TL_LANG']['MOD']['themeUpdate'][0] ?? 'Theme Updates',
            'csrf_token' => $this->csrfTokenManager->getDefaultTokenValue(),
            'error' => $error,
            'success' => null !== $result,
            'result' => $result,
        ]);
    }

    #[Route('/upload', name: 'theme_update_upload', methods: ['POST'])]
    public function upload(Request $request): RedirectResponse
    {
        $this->initializeContaoFramework();

        System::loadLanguageFile('tl_theme_update');

        $file = $request->files->get('theme_zip');

        if (null === $file || !$file->isValid()) {
            $this->addFlash('theme_update_error', $this->translator->trans('noFileUploaded', [], self::TRANSLATION_DOMAIN));

            return new RedirectResponse($this->generateUrl('theme_update_index'));
        }

        if ('application/zip' !== $file->getMimeType() && 'application/x-zip-compressed' !== $file->getMimeType()) {
            $this->addFlash('theme_update_error', $this->translator->trans('invalidFileType', [], self::TRANSLATION_DOMAIN));

            return new RedirectResponse($this->generateUrl('theme_update_index'));
        }

        $result = $this->updateService->processUpdate($file);

        if (!$result['success']) {
            $errorMessage = $this->translator->trans($result['error'], [], self::TRANSLATION_DOMAIN);

            if (!empty($result['errorDetail'])) {
                $errorMessage .= ': ' . $result['errorDetail'];
            }

            $this->addFlash('theme_update_error', $errorMessage);

            return new RedirectResponse($this->generateUrl('theme_update_index'));
        }

        $this->addFlash('theme_update_result', $result);

        return new RedirectResponse($this->generateUrl('theme_update_index'));
    }
}
