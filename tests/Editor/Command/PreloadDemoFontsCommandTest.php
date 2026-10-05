<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\Tests\Editor\Command;

use ErdmannFreunde\ThemeToolboxBundle\Editor\Command\PreloadDemoFontsCommand;
use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\GoogleFontBridge;
use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\TokenRegistry;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class PreloadDemoFontsCommandTest extends TestCase
{
    private string $tmp;

    private Filesystem $fs;

    private GoogleFontBridge&MockObject $fontBridge;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir().'/tt_preload_'.uniqid('', true);
        $this->fs = new Filesystem();
        $this->fs->mkdir([$this->tmp.'/layout/mytheme/scss', $this->tmp.'/layout/mytheme/presets']);
        file_put_contents($this->tmp.'/layout/mytheme/scss/default.scss', 'html{}');

        // Only the network boundary is stubbed; everything else is the real service.
        $this->fontBridge = $this->createMock(GoogleFontBridge::class);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmp);
    }

    public function testDownloadsExactlyTheFontsThePresetsAskFor(): void
    {
        $this->writePreset('bordeaux', [
            '--base-font-family' => "'Inter', 'Helvetica Neue', Helvetica, Arial, sans-serif",
        ]);
        $this->writePreset('smaragd', [
            '--base-font-family' => "'Nunito', 'Helvetica Neue', Helvetica, sans-serif",
        ]);

        $requested = [];
        $this->fontBridge
            ->method('import')
            ->willReturnCallback(
                static function (string $theme, string $family) use (&$requested): array {
                    $requested[] = $family;

                    return ['family' => $family, 'value' => '', 'weights' => ['400', '700'], 'faces' => []];
                },
            )
        ;

        $tester = $this->run_();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['Inter', 'Nunito'], $requested);
    }

    public function testAThemeWhosePresetsNeedNoFontDownloadsNothing(): void
    {
        $this->writePreset('beere', [
            '--base-font-family' => "system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif",
        ]);

        $this->fontBridge
            ->expects($this->never())
            ->method('import')
        ;

        $tester = $this->run_();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Nothing to preload', $this->display($tester));
    }

    public function testFamiliesOptionStillOverridesThePresets(): void
    {
        $this->writePreset('bordeaux', ['--base-font-family' => "'Inter', Arial, sans-serif"]);

        $requested = [];
        $this->fontBridge
            ->method('import')
            ->willReturnCallback(
                static function (string $theme, string $family) use (&$requested): array {
                    $requested[] = $family;

                    return ['family' => $family, 'value' => '', 'weights' => ['400'], 'faces' => []];
                },
            )
        ;

        $this->run_(['--families' => 'Space Grotesk, Roboto']);

        $this->assertSame(['Space Grotesk', 'Roboto'], $requested);
    }

    public function testAFailedDownloadIsReported(): void
    {
        $this->writePreset('smaragd', ['--base-font-family' => "'Nunito', Arial, sans-serif"]);

        $this->fontBridge
            ->method('import')
            ->willThrowException(new \RuntimeException('no outbound'))
        ;

        $tester = $this->run_();

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Nunito', $this->display($tester));
    }

    public function testLoadsTheThemeListPlusTheHeadingWeightsOfThePresets(): void
    {
        file_put_contents(
            $this->tmp.'/layout/mytheme/tokens.json',
            (string) json_encode(['fontWeights' => [400, 700]]),
        );
        $this->writePreset('bordeaux', [
            '--base-font-family' => "'Inter', Arial, sans-serif",
            '--headings-font-family' => "'Playfair Display', Georgia, serif",
            '--headings-font-weight' => '800',
        ]);

        $seen = [];
        $this->fontBridge
            ->method('import')
            ->willReturnCallback(
                static function (string $theme, string $family, array $extra = []) use (&$seen): array {
                    $seen[$family] = $extra;

                    return ['family' => $family, 'value' => '', 'weights' => ['400', '700'], 'faces' => []];
                },
            )
        ;

        $tester = $this->run_();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['800'], $seen['Playfair Display'] ?? null);
        $this->assertSame([], $seen['Inter'] ?? null, 'Der Schnitt gehört nur zur Überschriften-Schrift.');
    }

    public function testHeadingWeightsOfSeveralPresetsAreCollected(): void
    {
        $this->writePreset('eins', [
            '--headings-font-family' => "'Nunito', sans-serif",
            '--headings-font-weight' => '600',
        ]);
        $this->writePreset('zwei', [
            '--headings-font-family' => "'nunito', sans-serif",
            '--headings-font-weight' => '800',
        ]);

        $seen = [];
        $this->fontBridge
            ->method('import')
            ->willReturnCallback(
                static function (string $theme, string $family, array $extra = []) use (&$seen): array {
                    $seen[$family] = $extra;

                    return ['family' => $family, 'value' => '', 'weights' => ['400'], 'faces' => []];
                },
            )
        ;

        $this->run_();

        $this->assertSame(['600', '800'], $seen['Nunito'] ?? null);
    }

    /**
     * The console output with whitespace collapsed — SymfonyStyle wraps at the
     * terminal width and would otherwise split the text being asserted on.
     */
    private function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    /**
     * @param array<string, string> $values
     */
    private function writePreset(string $name, array $values): void
    {
        file_put_contents(
            $this->tmp.'/layout/mytheme/presets/'.$name.'.json',
            (string) json_encode(['name' => $name, 'values' => $values]),
        );
    }

    /**
     * @param array<string, string> $input
     */
    private function run_(array $input = []): CommandTester
    {
        $registry = new TokenRegistry(new ThemeScssFileManager($this->tmp, $this->fs, 'layout', 'layout/custom'));
        $tester = new CommandTester(new PreloadDemoFontsCommand($this->fontBridge, $registry));
        $tester->execute($input);

        return $tester;
    }
}
