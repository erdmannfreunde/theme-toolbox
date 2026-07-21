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

use Contao\Config;
use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\System;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeUpdateService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[Route('/contao/themeUpdate', defaults: ['_scope' => 'backend', '_token_check' => true])]
class ThemeUpdateController extends AbstractBackendController
{
    private const TRANSLATION_DOMAIN = 'contao_tl_theme_update';

    public function __construct(
        private readonly ThemeUpdateService $updateService,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly TranslatorInterface $translator,
        private readonly Environment $twig,
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

        $suggestCustom = null;
        $customResult = null;
        $dedupResult = null;

        if ($session instanceof FlashBagAwareSessionInterface) {
            $flashBag = $session->getFlashBag();
            $error = $flashBag->get('theme_update_error')[0] ?? null;
            $result = $flashBag->get('theme_update_result')[0] ?? null;
            $suggestCustom = $flashBag->get('theme_update_suggest_custom')[0] ?? null;
            $customResult = $flashBag->get('theme_update_custom_result')[0] ?? null;
            $dedupResult = $flashBag->get('theme_update_dedup_result')[0] ?? null;
        }

        return $this->render('@ErdmannFreundeThemeToolbox/backend/theme_update/index.html.twig', [
            'headline' => $GLOBALS['TL_LANG']['MOD']['themeUpdate'][0] ?? 'Theme Updates',
            'csrf_token' => $this->csrfTokenManager->getDefaultTokenValue(),
            'error' => $error,
            'success' => null !== $result,
            'result' => $result,
            'suggestCustom' => $suggestCustom,
            'customResult' => $customResult,
            'dedupResult' => $dedupResult,
            'has_favorites' => $this->twig->getLoader()->exists('@Contao/backend/component/_favorites.html.twig'),
        ]);
    }

    #[Route('/upload', name: 'theme_update_upload', methods: ['POST'])]
    public function upload(Request $request): RedirectResponse
    {
        $this->initializeContaoFramework();

        System::loadLanguageFile('tl_theme_update');

        $file = $request->files->get('theme_zip');

        if (null === $file) {
            $this->addFlash('theme_update_error', $this->translator->trans('noFileUploaded', [], self::TRANSLATION_DOMAIN));

            return new RedirectResponse($this->generateUrl('theme_update_index'));
        }

        if (!$file->isValid()) {
            $error = match ($file->getError()) {
                \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => $this->translator->trans(
                    'fileTooLarge',
                    [$this->formatBytes((int) UploadedFile::getMaxFilesize())],
                    self::TRANSLATION_DOMAIN,
                ),
                default => $this->translator->trans('uploadFailed', [], self::TRANSLATION_DOMAIN),
            };

            $this->addFlash('theme_update_error', $error);

            return new RedirectResponse($this->generateUrl('theme_update_index'));
        }

        $limit = min((int) UploadedFile::getMaxFilesize(), (int) Config::get('maxFileSize'));

        if ($limit > 0 && $file->getSize() > $limit) {
            $this->addFlash('theme_update_error', $this->translator->trans(
                'fileTooLarge',
                [$this->formatBytes($limit)],
                self::TRANSLATION_DOMAIN,
            ));

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

    #[Route('/continue', name: 'theme_update_continue', methods: ['POST'])]
    public function continueStep(Request $request): RedirectResponse
    {
        $this->initializeContaoFramework();

        $themeName = $request->request->getString('theme_name');
        $backupPath = $request->request->getString('backup_path');
        $updateStats = $request->request->getString('update_stats');

        $this->addFlash('theme_update_suggest_custom', [
            'themeName' => $themeName,
            'backupPath' => $backupPath,
            'updateStats' => $updateStats,
        ]);

        return new RedirectResponse($this->generateUrl('theme_update_index'));
    }

    #[Route('/copyCustomLayout', name: 'theme_update_copy_custom', methods: ['POST'])]
    public function copyCustomLayout(Request $request): RedirectResponse
    {
        $this->initializeContaoFramework();

        $themeName = $request->request->getString('theme_name');
        $backupPath = $request->request->getString('backup_path');
        $updateStats = $request->request->getString('update_stats');
        $copied = $this->updateService->copyToCustomLayout();

        $this->addFlash('theme_update_custom_result', [
            'copied' => $copied,
            'themeName' => $themeName,
            'backupPath' => $backupPath,
            'updateStats' => $updateStats,
        ]);

        return new RedirectResponse($this->generateUrl('theme_update_index'));
    }

    #[Route('/deduplicateCustomLayout', name: 'theme_update_deduplicate', methods: ['POST'])]
    public function deduplicateCustomLayout(Request $request): RedirectResponse
    {
        $this->initializeContaoFramework();

        $themeName = $request->request->getString('theme_name');
        $backupPath = $request->request->getString('backup_path');
        $updateStatsJson = $request->request->getString('update_stats');
        $removedFiles = $this->updateService->removeDuplicateFiles($themeName);

        $updateStats = [];
        if ('' !== $updateStatsJson) {
            $updateStats = json_decode($updateStatsJson, true) ?? [];
        }

        $this->addFlash('theme_update_dedup_result', [
            'removedFiles' => $removedFiles,
            'removed' => \count($removedFiles),
            'themeName' => $themeName,
            'backupPath' => $backupPath,
            'updateStats' => $updateStats,
        ]);

        return new RedirectResponse($this->generateUrl('theme_update_index'));
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, \count($units) - 1);

        return round($bytes / 1024 ** $power, 1) . ' ' . $units[$power];
    }
}
