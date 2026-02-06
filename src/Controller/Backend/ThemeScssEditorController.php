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

#[Route('/contao/themeScssEditor', defaults: ['_scope' => 'backend', '_token_check' => true])]
class ThemeScssEditorController extends AbstractBackendController
{
    public function __construct(
        private readonly ThemeScssFileManager $fileManager,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
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
        $originalContent = '';

        if ($selectedTheme && isset($themes[$selectedTheme])) {
            $files = $this->fileManager->getScssFiles($selectedTheme);

            if ($selectedFile) {
                $fileContent = $this->fileManager->getFileContent($selectedTheme, $selectedFile) ?? '';
                $isCustom = $this->fileManager->hasCustomFile($selectedFile);
                $originalContent = $this->fileManager->getOriginalFileContent($selectedTheme, $selectedFile) ?? '';
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
            'csrf_token' => $this->csrfTokenManager->getDefaultTokenValue(),
        ]);
    }

    #[Route('/save', name: 'theme_scss_editor_save', methods: ['POST'])]
    public function save(Request $request): JsonResponse
    {
        System::loadLanguageFile('tl_theme_scss');

        $theme = $request->request->get('theme', '');
        $file = $request->request->get('file', '');
        $content = $request->request->get('content', '');

        if (!$theme || !$file) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        $success = $this->fileManager->saveCustomFile($file, $content);

        return new JsonResponse([
            'success' => $success,
            'message' => $success
                ? ($GLOBALS['TL_LANG']['tl_theme_scss']['saved'] ?? 'File saved successfully')
                : ($GLOBALS['TL_LANG']['tl_theme_scss']['saveError'] ?? 'Error saving file'),
            'isCustom' => true,
        ]);
    }

    #[Route('/revert', name: 'theme_scss_editor_revert', methods: ['POST'])]
    public function revert(Request $request): JsonResponse
    {
        System::loadLanguageFile('tl_theme_scss');

        $theme = $request->request->get('theme', '');
        $file = $request->request->get('file', '');

        if (!$theme || !$file) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        $success = $this->fileManager->deleteCustomFile($file);
        $originalContent = $this->fileManager->getOriginalFileContent($theme, $file);

        return new JsonResponse([
            'success' => $success,
            'message' => $success
                ? ($GLOBALS['TL_LANG']['tl_theme_scss']['reverted'] ?? 'File reverted to original')
                : ($GLOBALS['TL_LANG']['tl_theme_scss']['revertError'] ?? 'Error reverting file'),
            'content' => $originalContent,
            'isCustom' => false,
        ]);
    }

    #[Route('/content', name: 'theme_scss_editor_content', defaults: ['_token_check' => false], methods: ['GET'])]
    public function getContent(Request $request): JsonResponse
    {
        $theme = $request->query->get('theme', '');
        $file = $request->query->get('file', '');

        if (!$theme || !$file) {
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
