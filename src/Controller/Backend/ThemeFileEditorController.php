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
use ErdmannFreunde\ThemeToolboxBundle\Service\GoogleFontsService;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeImageFileManager;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeJsFileManager;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/contao/themeFileEditor', defaults: ['_scope' => 'backend', '_token_check' => true])]
class ThemeFileEditorController extends AbstractBackendController
{
    private const TRANSLATION_DOMAIN = 'contao_tl_theme_file_editor';

    public function __construct(
        private readonly ThemeScssFileManager $fileManager,
        private readonly GoogleFontsService $googleFontsService,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly TranslatorInterface $translator,
        private readonly ThemeImageFileManager $imageFileManager,
        private readonly ThemeJsFileManager $jsFileManager,
    ) {
    }

    #[Route('', name: 'theme_file_editor_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->initializeContaoFramework();

        // Load backend CSS
        $GLOBALS['TL_CSS'][] = 'bundles/erdmannfreundethemetoolbox/css/theme_file_editor.css';

        System::loadLanguageFile('default');
        System::loadLanguageFile('modules');
        System::loadLanguageFile('tl_theme_file_editor');

        $activeTab = $request->query->get('tab', 'styles');

        if (!\in_array($activeTab, ['styles', 'webfonts', 'images', 'javascript'], true)) {
            $activeTab = 'styles';
        }

        if ('webfonts' === $activeTab) {
            $GLOBALS['TL_CSS'][] = 'bundles/erdmannfreundethemetoolbox/css/theme_webfonts.css';
        }

        if ('images' === $activeTab) {
            $GLOBALS['TL_CSS'][] = 'bundles/erdmannfreundethemetoolbox/css/theme_image_editor.css';
        }

        if ('javascript' === $activeTab) {
            $GLOBALS['TL_CSS'][] = 'bundles/erdmannfreundethemetoolbox/css/theme_file_editor.css';
        }

        $themes = $this->fileManager->getAvailableThemes();
        $selectedTheme = $request->query->get('theme', array_key_first($themes) ?? '');
        $selectedFile = $request->query->get('file', '');

        $files = [];
        $fileContent = '';
        $isCustom = false;
        $isCustomOnly = false;
        $originalContent = '';
        $imageFiles = [];
        $imageSelected = null;
        $jsFiles = [];

        if ($selectedTheme && isset($themes[$selectedTheme])) {
            if ('images' === $activeTab) {
                $imageFiles = $this->imageFileManager->getImageFiles($selectedTheme);

                if ($selectedFile) {
                    foreach ($imageFiles as $img) {
                        if ($img['path'] === $selectedFile) {
                            $imageSelected = $img;
                            break;
                        }
                    }
                }
            } elseif ('javascript' === $activeTab) {
                $jsFiles = $this->jsFileManager->getJsFiles($selectedTheme);

                if ($selectedFile) {
                    $fileContent = $this->jsFileManager->getFileContent($selectedTheme, $selectedFile) ?? '';
                    $isCustom = $this->jsFileManager->hasCustomFile($selectedFile);
                    $originalContent = $this->jsFileManager->getOriginalFileContent($selectedTheme, $selectedFile) ?? '';
                    $isCustomOnly = $isCustom && $originalContent === '';
                }
            } else {
                $files = $this->fileManager->getScssFiles($selectedTheme);

                if ($selectedFile) {
                    $fileContent = $this->fileManager->getFileContent($selectedTheme, $selectedFile) ?? '';
                    $isCustom = $this->fileManager->hasCustomFile($selectedFile);
                    $originalContent = $this->fileManager->getOriginalFileContent($selectedTheme, $selectedFile) ?? '';
                    $isCustomOnly = $isCustom && $originalContent === '';
                }
            }
        }

        return $this->render('@ErdmannFreundeThemeToolbox/backend/theme_file_editor/index.html.twig', [
            'headline' => $GLOBALS['TL_LANG']['MOD']['themeFileEditor'][0] ?? 'Theme SCSS Editor',
            'back_url' => $this->generateUrl('contao_backend'),
            'active_tab' => $activeTab,
            'themes' => $themes,
            'selected_theme' => $selectedTheme,
            'selected_file' => $selectedFile,
            'files' => $this->buildFileTree($files),
            'file_content' => $fileContent,
            'original_content' => $originalContent,
            'is_custom' => $isCustom,
            'is_custom_only' => $isCustomOnly,
            'image_files' => $imageFiles,
            'image_tree' => 'images' === $activeTab ? $this->buildImageTree($imageFiles, $selectedTheme && isset($themes[$selectedTheme]) ? $this->imageFileManager->getImageDirectories($selectedTheme) : []) : [],
            'image_selected' => $imageSelected,
            'js_tree' => 'javascript' === $activeTab ? $this->buildImageTree($jsFiles, $selectedTheme && isset($themes[$selectedTheme]) ? $this->jsFileManager->getJsDirectories($selectedTheme) : []) : [],
            'csrf_token' => $this->csrfTokenManager->getDefaultTokenValue(),
        ]);
    }

    private function isValidTheme(string $theme): bool
    {
        return '' !== $theme && isset($this->fileManager->getAvailableThemes()[$theme]);
    }

    #[Route('/save', name: 'theme_file_editor_save', methods: ['POST'])]
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

    #[Route('/revert', name: 'theme_file_editor_revert', methods: ['POST'])]
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

    #[Route('/rename', name: 'theme_file_editor_rename', methods: ['POST'])]
    public function rename(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $oldName = $request->request->get('oldName', '');
        $newName = $request->request->get('newName', '');

        if (!$this->isValidTheme($theme) || !$oldName || !$newName) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        // Validate new name
        if (!preg_match('/^[\w\-\/]+\.scss$/', $newName)) {
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

    #[Route('/create', name: 'theme_file_editor_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $directory = $request->request->get('directory', '');
        $fileName = $request->request->get('fileName', '');

        if (!$this->isValidTheme($theme) || !$fileName) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        // Validate file name
        if (!preg_match('/^[\w\-]+\.scss$/', $fileName)) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('invalidFileName', [], self::TRANSLATION_DOMAIN),
            ], 400);
        }

        $filePath = $directory ? $directory . '/' . $fileName : $fileName;

        if ($this->fileManager->hasCustomFile($filePath)) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('fileExists', [], self::TRANSLATION_DOMAIN),
            ], 400);
        }

        $success = $this->fileManager->saveCustomFile($filePath, '');

        return new JsonResponse([
            'success' => $success,
            'message' => $success
                ? $this->translator->trans('created', [], self::TRANSLATION_DOMAIN)
                : $this->translator->trans('createError', [], self::TRANSLATION_DOMAIN),
            'filePath' => $filePath,
        ]);
    }

    #[Route('/upload-font', name: 'theme_file_editor_upload_font', methods: ['POST'])]
    public function uploadFont(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $family = trim((string) $request->request->get('family', ''));
        $weight = trim((string) $request->request->get('weight', '400'));
        $style = strtolower(trim((string) $request->request->get('style', 'normal')));

        /** @var array<int, UploadedFile>|UploadedFile|null $uploaded */
        $uploaded = $request->files->get('files');
        $files = $uploaded instanceof UploadedFile ? [$uploaded] : (is_array($uploaded) ? $uploaded : []);

        if (!$this->isValidTheme($theme) || '' === $family || [] === $files) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        if (!preg_match('/^[1-9]00$/', $weight)) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('invalidFontWeight', [], self::TRANSLATION_DOMAIN),
            ], 400);
        }

        if (!\in_array($style, ['normal', 'italic'], true)) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('invalidFontStyle', [], self::TRANSLATION_DOMAIN),
            ], 400);
        }

        try {
            $result = $this->fileManager->saveUploadedFonts($family, $files);
            $scssBlock = $this->fileManager->appendFontFaceToCustomScss($theme, $family, $weight, $style, $result['files']);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }

        return new JsonResponse([
            'success' => true,
            'message' => $this->translator->trans('fontUploaded', [], self::TRANSLATION_DOMAIN),
            'scssBlock' => $scssBlock,
            'targetFile' => 'base/_fonts.scss',
            'files' => $result['files'],
        ]);
    }

    #[Route('/google-fonts-catalog', name: 'theme_file_editor_google_fonts_catalog', defaults: ['_token_check' => false], methods: ['GET'])]
    public function googleFontsCatalog(Request $request): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        $category = trim((string) $request->query->get('category', ''));
        $limit = max(1, min(100, (int) $request->query->get('limit', 25)));

        try {
            $fonts = $this->googleFontsService->getCatalog($search, $category, $limit);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }

        return new JsonResponse([
            'success' => true,
            'fonts' => $fonts,
        ]);
    }

    #[Route('/import-google-font', name: 'theme_file_editor_import_google_font', methods: ['POST'])]
    public function importGoogleFont(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $family = trim((string) $request->request->get('family', ''));
        $weight = trim((string) $request->request->get('weight', '400'));
        $style = strtolower(trim((string) $request->request->get('style', 'normal')));

        if (!$this->isValidTheme($theme) || '' === $family) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        if (!preg_match('/^[1-9]00$/', $weight)) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('invalidFontWeight', [], self::TRANSLATION_DOMAIN),
            ], 400);
        }

        if (!\in_array($style, ['normal', 'italic'], true)) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('invalidFontStyle', [], self::TRANSLATION_DOMAIN),
            ], 400);
        }

        try {
            if ($this->fileManager->hasFontFaceDefinition($theme, $family, $weight, $style)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Dieser Schriftschnitt ist bereits vorhanden.',
                    'scssBlock' => '',
                    'targetFile' => 'base/_fonts.scss',
                    'files' => [],
                ]);
            }

            $downloaded = $this->googleFontsService->downloadFontFiles($family, $weight, $style);
            $result = $this->fileManager->saveBinaryFonts($family, $downloaded['files']);
            $scssBlock = $this->fileManager->appendFontFaceToCustomScss($theme, $family, $weight, $style, $result['files']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }

        return new JsonResponse([
            'success' => true,
            'message' => $this->translator->trans('fontUploaded', [], self::TRANSLATION_DOMAIN),
            'scssBlock' => $scssBlock,
            'targetFile' => 'base/_fonts.scss',
            'files' => $result['files'],
        ]);
    }

    #[Route('/cleanup-unused-font-faces', name: 'theme_file_editor_cleanup_unused_font_faces', methods: ['POST'])]
    public function cleanupUnusedFontFaces(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');

        if (!$this->isValidTheme($theme)) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        try {
            $result = $this->fileManager->cleanupUnusedFontFaces($theme);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }

        return new JsonResponse([
            'success' => true,
            'removedBlocks' => $result['removedBlocks'],
            'removedFamilies' => $result['removedFamilies'],
            'keptBlocks' => $result['keptBlocks'],
        ]);
    }

    #[Route('/delete', name: 'theme_file_editor_delete', methods: ['POST'])]
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

    #[Route('/content', name: 'theme_file_editor_content', defaults: ['_token_check' => false], methods: ['GET'])]
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

    #[Route('/images/upload', name: 'theme_image_editor_upload', methods: ['POST'])]
    public function imageUpload(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $directory = trim((string) $request->request->get('directory', ''), '/');
        $targetName = trim((string) $request->request->get('targetName', ''));

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');

        if (!$this->isValidTheme($theme) || !$file instanceof UploadedFile) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        $fileName = '' !== $targetName ? $targetName : $file->getClientOriginalName();

        if (!preg_match('/^[\w\-. ]+\.[A-Za-z0-9]+$/', $fileName)) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('invalidFileName', [], self::TRANSLATION_DOMAIN),
            ], 400);
        }

        $relativePath = $directory !== '' ? $directory . '/' . $fileName : $fileName;

        try {
            $this->imageFileManager->saveUploadedImage($relativePath, $file);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }

        return new JsonResponse([
            'success' => true,
            'message' => $this->translator->trans('imageUploaded', [], self::TRANSLATION_DOMAIN),
            'filePath' => $relativePath,
        ]);
    }

    #[Route('/images/delete', name: 'theme_image_editor_delete', methods: ['POST'])]
    public function imageDelete(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $file = $request->request->get('file', '');

        if (!$this->isValidTheme($theme) || !$file) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        try {
            $success = $this->imageFileManager->deleteCustomFile($file);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }

        return new JsonResponse([
            'success' => $success,
            'message' => $success
                ? $this->translator->trans('deleted', [], self::TRANSLATION_DOMAIN)
                : $this->translator->trans('deleteError', [], self::TRANSLATION_DOMAIN),
        ]);
    }

    #[Route('/images/revert', name: 'theme_image_editor_revert', methods: ['POST'])]
    public function imageRevert(Request $request): JsonResponse
    {
        return $this->imageDelete($request);
    }

    #[Route('/images/rename', name: 'theme_image_editor_rename', methods: ['POST'])]
    public function imageRename(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $oldName = $request->request->get('oldName', '');
        $newName = $request->request->get('newName', '');

        if (!$this->isValidTheme($theme) || !$oldName || !$newName) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        if (!preg_match('/^[\w\-. \/]+\.[A-Za-z0-9]+$/', $newName)) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('invalidFileName', [], self::TRANSLATION_DOMAIN),
            ], 400);
        }

        try {
            $success = $this->imageFileManager->renameCustomFile($oldName, $newName);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }

        return new JsonResponse([
            'success' => $success,
            'message' => $success
                ? $this->translator->trans('renamed', [], self::TRANSLATION_DOMAIN)
                : $this->translator->trans('renameError', [], self::TRANSLATION_DOMAIN),
            'newName' => $newName,
        ]);
    }

    #[Route('/images/create-folder', name: 'theme_image_editor_create_folder', methods: ['POST'])]
    public function imageCreateFolder(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $directory = trim((string) $request->request->get('directory', ''), '/');
        $folderName = trim((string) $request->request->get('folderName', ''));

        if (!$this->isValidTheme($theme) || '' === $folderName) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        if (!preg_match('/^[\w\-. ]+$/', $folderName)) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('invalidFolderName', [], self::TRANSLATION_DOMAIN),
            ], 400);
        }

        $relativePath = '' !== $directory ? $directory . '/' . $folderName : $folderName;

        try {
            $this->imageFileManager->createCustomFolder($relativePath);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }

        return new JsonResponse([
            'success' => true,
            'message' => $this->translator->trans('folderCreated', [], self::TRANSLATION_DOMAIN),
            'directory' => $relativePath,
        ]);
    }

    #[Route('/images/serve', name: 'theme_image_editor_serve', defaults: ['_token_check' => false], methods: ['GET'])]
    public function imageServe(Request $request): Response
    {
        $theme = $request->query->get('theme', '');
        $file = $request->query->get('file', '');
        $source = $request->query->get('source', 'current');

        if (!$this->isValidTheme($theme) || !$file) {
            return new Response('', 404);
        }

        try {
            $path = 'original' === $source
                ? $this->imageFileManager->getOriginalServablePath($theme, $file)
                : $this->imageFileManager->getServablePath($theme, $file);
        } catch (\InvalidArgumentException) {
            return new Response('', 400);
        }

        if (null === $path) {
            return new Response('', 404);
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    #[Route('/js/save', name: 'theme_js_editor_save', methods: ['POST'])]
    public function jsSave(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $file = $request->request->get('file', '');
        $content = $request->request->get('content', '');

        if (!$this->isValidTheme($theme) || !$file) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        $success = $this->jsFileManager->saveCustomFile($file, $content);

        return new JsonResponse([
            'success' => $success,
            'message' => $success
                ? $this->translator->trans('saved', [], self::TRANSLATION_DOMAIN)
                : $this->translator->trans('saveError', [], self::TRANSLATION_DOMAIN),
            'isCustom' => true,
        ]);
    }

    #[Route('/js/revert', name: 'theme_js_editor_revert', methods: ['POST'])]
    public function jsRevert(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $file = $request->request->get('file', '');

        if (!$this->isValidTheme($theme) || !$file) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        $success = $this->jsFileManager->deleteCustomFile($file);
        $originalContent = $this->jsFileManager->getOriginalFileContent($theme, $file);

        return new JsonResponse([
            'success' => $success,
            'message' => $success
                ? $this->translator->trans('reverted', [], self::TRANSLATION_DOMAIN)
                : $this->translator->trans('revertError', [], self::TRANSLATION_DOMAIN),
            'content' => $originalContent,
            'isCustom' => false,
        ]);
    }

    #[Route('/js/rename', name: 'theme_js_editor_rename', methods: ['POST'])]
    public function jsRename(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $oldName = $request->request->get('oldName', '');
        $newName = $request->request->get('newName', '');

        if (!$this->isValidTheme($theme) || !$oldName || !$newName) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        if (!preg_match('/^[\w\-\/]+\.js$/', $newName)) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('invalidFileName', [], self::TRANSLATION_DOMAIN),
            ], 400);
        }

        $success = $this->jsFileManager->renameCustomFile($oldName, $newName);

        return new JsonResponse([
            'success' => $success,
            'message' => $success
                ? $this->translator->trans('renamed', [], self::TRANSLATION_DOMAIN)
                : $this->translator->trans('renameError', [], self::TRANSLATION_DOMAIN),
            'newName' => $newName,
        ]);
    }

    #[Route('/js/create', name: 'theme_js_editor_create', methods: ['POST'])]
    public function jsCreate(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $directory = $request->request->get('directory', '');
        $fileName = $request->request->get('fileName', '');

        if (!$this->isValidTheme($theme) || !$fileName) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        if (!preg_match('/^[\w\-]+\.js$/', $fileName)) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('invalidFileName', [], self::TRANSLATION_DOMAIN),
            ], 400);
        }

        $filePath = $directory ? $directory . '/' . $fileName : $fileName;

        if ($this->jsFileManager->hasCustomFile($filePath)) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('fileExists', [], self::TRANSLATION_DOMAIN),
            ], 400);
        }

        $success = $this->jsFileManager->saveCustomFile($filePath, '');

        return new JsonResponse([
            'success' => $success,
            'message' => $success
                ? $this->translator->trans('created', [], self::TRANSLATION_DOMAIN)
                : $this->translator->trans('createError', [], self::TRANSLATION_DOMAIN),
            'filePath' => $filePath,
        ]);
    }

    #[Route('/js/delete', name: 'theme_js_editor_delete', methods: ['POST'])]
    public function jsDelete(Request $request): JsonResponse
    {
        $theme = $request->request->get('theme', '');
        $file = $request->request->get('file', '');

        if (!$this->isValidTheme($theme) || !$file) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        $success = $this->jsFileManager->deleteCustomFile($file);

        return new JsonResponse([
            'success' => $success,
            'message' => $success
                ? $this->translator->trans('deleted', [], self::TRANSLATION_DOMAIN)
                : $this->translator->trans('deleteError', [], self::TRANSLATION_DOMAIN),
        ]);
    }

    /**
     * Build a tree including empty directories.
     *
     * @param array<int, array<string, mixed>> $files
     * @param list<string>                     $directories
     *
     * @return array<string, mixed>
     */
    private function buildImageTree(array $files, array $directories): array
    {
        $tree = $this->buildFileTree($files);

        foreach ($directories as $dirPath) {
            $parts = explode('/', $dirPath);
            $current = &$tree;

            foreach ($parts as $part) {
                if (!isset($current[$part])) {
                    $current[$part] = ['_files' => []];
                }
                $current = &$current[$part];
            }

            unset($current);
        }

        return $tree;
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
