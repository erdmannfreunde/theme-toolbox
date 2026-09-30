<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\Tests\Service;

use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeUpdateService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ThemeUpdateServiceTest extends TestCase
{
    private const THEME = 'solo-theme';

    private string $tmp;

    private string $projectDir;

    private Filesystem $fs;

    private ThemeUpdateService $service;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir().'/tt_update_'.uniqid('', true);
        $this->projectDir = $this->tmp.'/project';
        $this->fs = new Filesystem();

        // A Contao project always has these
        $this->fs->mkdir([$this->projectDir.'/files', $this->projectDir.'/templates', $this->projectDir.'/layout']);

        $this->service = new ThemeUpdateService($this->projectDir, $this->fs);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmp);
    }

    // ---------------------------------------------------------------- #39

    public function testSqlDumpFromThePackageIsNotCopiedIntoTemplates(): void
    {
        $this->installTheme();
        $result = $this->runUpdate($this->buildPackage());

        $this->assertTrue($result['success']);
        $this->assertFileDoesNotExist($this->projectDir.'/templates/backup__20260929203840.sql');
    }

    public function testRealTemplatesFromThePackageAreStillCopied(): void
    {
        $this->installTheme();
        $result = $this->runUpdate($this->buildPackage(templates: [
            'backup__20260929203840.sql' => '-- dump',
            'ce_headline.html.twig' => '<h1></h1>',
        ]));

        $this->assertSame(1, $result['stats']['templatesCopied']);
        $this->assertFileExists($this->projectDir.'/templates/ce_headline.html.twig');
        $this->assertFileDoesNotExist($this->projectDir.'/templates/backup__20260929203840.sql');
    }

    public function testNestedSqlDumpIsSkippedToo(): void
    {
        $this->installTheme();
        $this->runUpdate($this->buildPackage(templates: ['demo/backup__1.sql' => '-- dump']));

        $this->assertFileDoesNotExist($this->projectDir.'/templates/demo/backup__1.sql');
    }

    public function testExistingSqlDumpIsLeftAloneButKeptOutOfTheBackup(): void
    {
        $this->installTheme();
        file_put_contents($this->projectDir.'/templates/backup__old.sql', '-- previously collected');

        $result = $this->runUpdate($this->buildPackage());

        // The update never deletes from templates/
        $this->assertFileExists($this->projectDir.'/templates/backup__old.sql');
        $this->assertNotContains('templates/backup__old.sql', $this->backupEntries($result['backupPath']));
    }

    // ---------------------------------------------------------------- #41

    public function testBackupOnlyCoversTheFilesSubDirectoriesTheUpdateWrites(): void
    {
        $this->installTheme();
        $this->fs->mkdir($this->projectDir.'/files/kunde/fotostrecke');
        file_put_contents($this->projectDir.'/files/kunde/fotostrecke/gross.jpg', 'x');

        $result = $this->runUpdate($this->buildPackage());
        $entries = $this->backupEntries($result['backupPath']);

        $this->assertContains('files/demo/bild.jpg', $entries);
        $this->assertNotContains('files/kunde/fotostrecke/gross.jpg', $entries);
    }

    public function testBackupKeepsOnlyTheMostRecentArchives(): void
    {
        $this->installTheme();
        $backupDir = $this->projectDir.'/var/backups/theme-updates';
        $this->fs->mkdir($backupDir);

        foreach (['2026-01-01_10-00-00', '2026-02-01_10-00-00', '2026-03-01_10-00-00', '2026-04-01_10-00-00'] as $stamp) {
            file_put_contents($backupDir.'/'.self::THEME.'-'.$stamp.'.zip', 'alt');
        }

        $this->runUpdate($this->buildPackage());

        $remaining = $this->backupArchives($backupDir);

        $this->assertCount(3, $remaining);
        $this->assertNotContains(self::THEME.'-2026-01-01_10-00-00.zip', $remaining);
        $this->assertNotContains(self::THEME.'-2026-02-01_10-00-00.zip', $remaining);
        $this->assertContains(self::THEME.'-2026-04-01_10-00-00.zip', $remaining);
    }

    public function testPruningNeverRemovesTheArchiveJustCreated(): void
    {
        $this->installTheme();
        $backupDir = $this->projectDir.'/var/backups/theme-updates';
        $this->fs->mkdir($backupDir);

        // Archives that sort AFTER the fresh one, as a clock moved backwards would produce
        foreach (['2099-01-01_10-00-00', '2099-02-01_10-00-00', '2099-03-01_10-00-00'] as $stamp) {
            file_put_contents($backupDir.'/'.self::THEME.'-'.$stamp.'.zip', 'zukunft');
        }

        $result = $this->runUpdate($this->buildPackage());

        $this->assertNotSame('', $result['backupPath']);
        $this->assertFileExists($this->projectDir.'/'.$result['backupPath']);
    }

    public function testPruningLeavesOtherThemesAndForeignFilesAlone(): void
    {
        $this->installTheme();
        $backupDir = $this->projectDir.'/var/backups/theme-updates';
        $this->fs->mkdir($backupDir);

        foreach (['2026-01-01_10-00-00', '2026-02-01_10-00-00', '2026-03-01_10-00-00'] as $stamp) {
            file_put_contents($backupDir.'/lasr-theme-'.$stamp.'.zip', 'fremd');
        }
        file_put_contents($backupDir.'/README.txt', 'nicht anfassen');

        $this->runUpdate($this->buildPackage());

        $this->assertCount(3, $this->backupArchives($backupDir, 'lasr-theme-'));
        $this->assertFileExists($backupDir.'/README.txt');
    }

    /**
     * "solo-theme" starts with "solo", so a glob would pull the other theme's
     * archives into the same retention pot — and they would even outlive the
     * theme's own ones, because a digit sorts before a letter.
     */
    public function testPruningIgnoresThemesWhoseNameSharesThePrefix(): void
    {
        $this->installTheme();
        $backupDir = $this->projectDir.'/var/backups/theme-updates';
        $this->fs->mkdir($backupDir);

        foreach (['2026-01-01_10-00-00', '2026-02-01_10-00-00', '2026-03-01_10-00-00'] as $stamp) {
            file_put_contents($backupDir.'/'.self::THEME.'-variant-'.$stamp.'.zip', 'fremdes theme');
        }

        foreach (['2026-01-01_10-00-00', '2026-02-01_10-00-00'] as $stamp) {
            file_put_contents($backupDir.'/'.self::THEME.'-'.$stamp.'.zip', 'eigenes theme');
        }

        $this->runUpdate($this->buildPackage());

        $this->assertCount(3, $this->backupArchives($backupDir, self::THEME.'-variant-'));
        $this->assertCount(3, $this->backupArchives($backupDir, self::THEME.'-2'));
    }

    public function testHandNamedArchiveIsNeitherCountedNorRemoved(): void
    {
        $this->installTheme();
        $backupDir = $this->projectDir.'/var/backups/theme-updates';
        $this->fs->mkdir($backupDir);

        file_put_contents($backupDir.'/'.self::THEME.'-vor-relaunch.zip', 'von Hand abgelegt');

        foreach (['2026-01-01_10-00-00', '2026-02-01_10-00-00', '2026-03-01_10-00-00'] as $stamp) {
            file_put_contents($backupDir.'/'.self::THEME.'-'.$stamp.'.zip', 'alt');
        }

        $this->runUpdate($this->buildPackage());

        $this->assertFileExists($backupDir.'/'.self::THEME.'-vor-relaunch.zip');
        $this->assertCount(3, $this->backupArchives($backupDir, self::THEME.'-2'));
    }

    /**
     * A run that fails after layout/<theme> was created must not make every later
     * attempt look like an update — the demo files would never be installed.
     */
    public function testEmptyLayoutDirectoryFromAnAbortedRunStillCountsAsFirstRun(): void
    {
        $this->fs->mkdir($this->projectDir.'/layout/'.self::THEME);

        $result = $this->runUpdate($this->buildPackage());

        $this->assertTrue($result['success']);
        $this->assertFileExists($this->projectDir.'/files/demo/bild.jpg');
        $this->assertFileExists($this->projectDir.'/files/demo/.public');
    }

    // ---------------------------------------------------------------- #42

    public function testRemovedDemoFolderIsNotRecreatedOnUpdate(): void
    {
        $this->installTheme();
        $this->fs->remove($this->projectDir.'/files/demo');

        $result = $this->runUpdate($this->buildPackage());

        $this->assertTrue($result['success']);
        $this->assertDirectoryDoesNotExist($this->projectDir.'/files/demo');
        $this->assertSame(0, $result['stats']['filesCopied']);
    }

    public function testExistingDemoFolderIsStillUpdated(): void
    {
        $this->installTheme();
        file_put_contents($this->projectDir.'/files/demo/bild.jpg', 'alt');

        $this->runUpdate($this->buildPackage());

        $this->assertSame('neu', file_get_contents($this->projectDir.'/files/demo/bild.jpg'));
    }

    public function testPublicMarkerIsCopiedAlong(): void
    {
        $this->installTheme();
        $this->fs->remove($this->projectDir.'/files/demo/.public');

        $this->runUpdate($this->buildPackage());

        $this->assertFileExists($this->projectDir.'/files/demo/.public');
    }

    public function testPublicMarkerIsIncludedInTheBackup(): void
    {
        $this->installTheme();
        $result = $this->runUpdate($this->buildPackage());

        $this->assertContains('files/demo/.public', $this->backupEntries($result['backupPath']));
    }

    public function testFirstRunInstallsTheDemoFilesCompletely(): void
    {
        // No layout/<theme> and no files/demo yet
        $result = $this->runUpdate($this->buildPackage());

        $this->assertTrue($result['success']);
        $this->assertFileExists($this->projectDir.'/files/demo/bild.jpg');
        $this->assertFileExists($this->projectDir.'/files/demo/.public');
    }

    public function testFirstRunWithoutAnythingToBackUpReportsNoBackupPath(): void
    {
        $result = $this->runUpdate($this->buildPackage());

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['backupPath']);
    }

    // ------------------------------------------------- Traversal-Schutz

    public function testThemeNameThatWouldEscapeTheLayoutDirectoryIsRejected(): void
    {
        file_put_contents($this->projectDir.'/composer.json', '{"name":"kunde/projekt"}');

        $zipPath = $this->tmp.'/evil.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('PKG/layout/../marker.txt', 'boom');
        $zip->close();

        $result = $this->runUpdate($zipPath);

        $this->assertFalse($result['success']);
        $this->assertSame('noThemeDetected', $result['error']);
        $this->assertFileExists($this->projectDir.'/composer.json');
    }

    // ----------------------------------------------------------- Helfer

    /**
     * Puts the theme into the project the way a previous update would have left it.
     */
    private function installTheme(): void
    {
        $this->fs->mkdir($this->projectDir.'/layout/'.self::THEME.'/scss');
        file_put_contents($this->projectDir.'/layout/'.self::THEME.'/scss/default.scss', 'body{}');
        $this->fs->mkdir($this->projectDir.'/files/demo');
        file_put_contents($this->projectDir.'/files/demo/bild.jpg', 'alt');
        file_put_contents($this->projectDir.'/files/demo/.public', '');
    }

    /**
     * Builds a ZIP shaped like the real SE packages.
     *
     * @param array<string, string>|null $templates
     */
    private function buildPackage(array|null $templates = null): string
    {
        $templates ??= ['backup__20260929203840.sql' => '-- demo dump'];

        $zipPath = $this->tmp.'/package-'.uniqid('', true).'.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);

        $zip->addFromString('SOLO-THEME/layout/'.self::THEME.'/scss/default.scss', 'body{color:red}');
        $zip->addFromString('SOLO-THEME/layout/'.self::THEME.'/fonts/.public', '');
        $zip->addFromString('SOLO-THEME/files/demo/bild.jpg', 'neu');
        $zip->addFromString('SOLO-THEME/files/demo/.public', '');

        foreach ($templates as $name => $content) {
            $zip->addFromString('SOLO-THEME/templates/'.$name, $content);
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * @return array{success: bool, themeName: string, backupPath: string, stats: array<string, int>, error?: string}
     */
    private function runUpdate(string $zipPath): array
    {
        return $this->service->processUpdate(new UploadedFile($zipPath, basename($zipPath), null, null, true));
    }

    /**
     * @return list<string>
     */
    private function backupEntries(string $relativeBackupPath): array
    {
        $this->assertNotSame('', $relativeBackupPath, 'Es wurde kein Backup angelegt.');

        $zip = new \ZipArchive();
        $zip->open($this->projectDir.'/'.$relativeBackupPath);

        $entries = [];

        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $entries[] = $zip->getNameIndex($i);
        }

        $zip->close();

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function backupArchives(string $backupDir, string $prefix = self::THEME.'-'): array
    {
        $names = array_map('basename', glob($backupDir.'/'.$prefix.'*.zip') ?: []);
        sort($names);

        return $names;
    }
}
