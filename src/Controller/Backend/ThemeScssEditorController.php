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
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/contao/themeScssEditor', defaults: ['_scope' => 'backend', '_token_check' => true])]
class ThemeScssEditorController extends AbstractBackendController
{
    private const TRANSLATION_DOMAIN = 'contao_tl_theme_scss';

    public function __construct(
        private readonly ThemeScssFileManager $fileManager,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'theme_scss_editor_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->initializeContaoFramework();

        System::loadLanguageFile('default');
        System::loadLanguageFile('modules');
        System::loadLanguageFile('tl_theme_scss');

        $themes = $this->fileManager->getAvailableThemes();
        $selectedTheme = $request->query->get('theme', array_key_first($themes) ?? '');
        $selectedFile = $request->query->get('file', '');

        $files = [];
        $fileContent = '';
        $isCustom = false;
        $isCustomOnly = false;
        $originalContent = '';

        if ($selectedTheme && isset($themes[$selectedTheme])) {
            $files = $this->fileManager->getScssFiles($selectedTheme);

            if ($selectedFile) {
                $fileContent = $this->fileManager->getFileContent($selectedTheme, $selectedFile) ?? '';
                $isCustom = $this->fileManager->hasCustomFile($selectedFile);
                $originalContent = $this->fileManager->getOriginalFileContent($selectedTheme, $selectedFile) ?? '';
                // Check if this is a custom-only file (no original exists)
                $isCustomOnly = $isCustom && $originalContent === '';
            }
        }

        return $this->render('@ErdmannFreundeThemeToolbox/backend/theme_scss_editor/index.html.twig', [
            'headline' => $GLOBALS['TL_LANG']['MOD']['themeScssEditor'][0] ?? 'Theme SCSS Editor',
            'back_url' => $this->generateUrl('contao_backend'),
            'themes' => $themes,
            'selected_theme' => $selectedTheme,
            'selected_file' => $selectedFile,
            'files' => $this->buildFileTree($files),
            'file_content' => $fileContent,
            'original_content' => $originalContent,
            'is_custom' => $isCustom,
            'is_custom_only' => $isCustomOnly,
            'csrf_token' => $this->csrfTokenManager->getDefaultTokenValue(),
        ]);
    }

    private function isValidTheme(string $theme): bool
    {
        return '' !== $theme && isset($this->fileManager->getAvailableThemes()[$theme]);
    }

    #[Route('/save', name: 'theme_scss_editor_save', methods: ['POST'])]
    public function save(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $file = $request->request->get('file', '');
        $content = $request->request->get('content', '');

        if (!$this->isValidTheme($theme) || !$file) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        $success = $this->fileManager->saveCustomFile($file, $content);

        return new JsonResponse([
            'success' => $success,
            'message' => $success
                ? $this->translator->trans('saved', [], self::TRANSLATION_DOMAIN)
                : $this->translator->trans('saveError', [], self::TRANSLATION_DOMAIN),
            'isCustom' => true,
        ]);
    }

    #[Route('/revert', name: 'theme_scss_editor_revert', methods: ['POST'])]
    public function revert(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $file = $request->request->get('file', '');

        if (!$this->isValidTheme($theme) || !$file) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        $success = $this->fileManager->deleteCustomFile($file);
        $originalContent = $this->fileManager->getOriginalFileContent($theme, $file);

        return new JsonResponse([
            'success' => $success,
            'message' => $success
                ? $this->translator->trans('reverted', [], self::TRANSLATION_DOMAIN)
                : $this->translator->trans('revertError', [], self::TRANSLATION_DOMAIN),
            'content' => $originalContent,
            'isCustom' => false,
        ]);
    }

    #[Route('/rename', name: 'theme_scss_editor_rename', methods: ['POST'])]
    public function rename(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $oldName = $request->request->get('oldName', '');
        $newName = $request->request->get('newName', '');

        if (!$this->isValidTheme($theme) || !$oldName || !$newName) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        // Validate new name
        if (!preg_match('/^[\w\-]+\.scss$/', $newName)) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('invalidFileName', [], self::TRANSLATION_DOMAIN),
            ], 400);
        }

        $success = $this->fileManager->renameCustomFile($oldName, $newName);

        return new JsonResponse([
            'success' => $success,
            'message' => $success
                ? $this->translator->trans('renamed', [], self::TRANSLATION_DOMAIN)
                : $this->translator->trans('renameError', [], self::TRANSLATION_DOMAIN),
            'newName' => $newName,
        ]);
    }

    #[Route('/delete', name: 'theme_scss_editor_delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $file = $request->request->get('file', '');

        if (!$this->isValidTheme($theme) || !$file) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        $success = $this->fileManager->deleteCustomFile($file);

        return new JsonResponse([
            'success' => $success,
            'message' => $success
                ? $this->translator->trans('deleted', [], self::TRANSLATION_DOMAIN)
                : $this->translator->trans('deleteError', [], self::TRANSLATION_DOMAIN),
        ]);
    }

    #[Route('/content', name: 'theme_scss_editor_content', defaults: ['_token_check' => false], methods: ['GET'])]
    public function getContent(Request $request): JsonResponse
    {
        $theme = $request->query->get('theme', '');
        $file = $request->query->get('file', '');

        if (!$this->isValidTheme($theme) || !$file) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        $content = $this->fileManager->getFileContent($theme, $file);
        $originalContent = $this->fileManager->getOriginalFileContent($theme, $file);
        $isCustom = $this->fileManager->hasCustomFile($file);

        return new JsonResponse([
            'success' => true,
            'content' => $content,
            'originalContent' => $originalContent,
            'isCustom' => $isCustom,
        ]);
    }

    /**
     * Build a tree structure from flat file list.
     *
     * @param array<int, array{path: string, name: string, directory: string, isCustom: bool, hasCustom: bool}> $files
     *
     * @return array<string, mixed>
     */
    private function buildFileTree(array $files): array
    {
        $tree = [];

        foreach ($files as $file) {
            $parts = explode('/', $file['path']);
            $current = &$tree;

            foreach ($parts as $i => $part) {
                if ($i === \count($parts) - 1) {
                    $current['_files'][] = $file;
                } else {
                    if (!isset($current[$part])) {
                        $current[$part] = ['_files' => []];
                    }
                    $current = &$current[$part];
                }
            }
        }

        return $tree;
    }
}
