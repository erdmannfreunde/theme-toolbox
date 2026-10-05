<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\Tests\Editor\Service;

use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\GoogleFontBridge;
use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\TokenRegistry;
use ErdmannFreunde\ThemeToolboxBundle\Service\GoogleFontsService;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssCompiler;
use ErdmannFreunde\ThemeToolboxBundle\Service\ThemeScssFileManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class GoogleFontBridgeTest extends TestCase
{
    private const THEME = 'mytheme';

    private string $tmp;

    private Filesystem $fs;

    private GoogleFontsService&MockObject $googleFonts;

    private ThemeScssFileManager $fileManager;

    /**
     * @var list<string> Weights actually asked of the download service, in order
     */
    private array $requested = [];

    /**
     * @var list<string> Weights the fake family does not offer
     */
    private array $unavailable = [];

    private bool $downloadsFail = false;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir().'/tt_bridge_'.uniqid('', true);
        $this->fs = new Filesystem();
        $this->fs->mkdir($this->tmp.'/layout/'.self::THEME.'/scss/base');
        file_put_contents($this->tmp.'/layout/'.self::THEME.'/scss/default.scss', 'html{}');
        file_put_contents($this->tmp.'/layout/'.self::THEME.'/scss/base/_fonts.scss', "// fonts\n");

        // Only the network boundary is stubbed; everything else is the real service.
        $this->googleFonts = $this->createMock(GoogleFontsService::class);
        $this->fileManager = new ThemeScssFileManager($this->tmp, $this->fs, 'layout', 'layout/custom');
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmp);
    }

    public function testWeightsComeFromTheRegistry(): void
    {
        $this->writeThemeTokens(['fontWeights' => [400, 600, 700, 800]]);

        $result = $this->bridge()->import(self::THEME, 'Inter');

        $this->assertSame(['400', '600', '700', '800'], $result['weights']);
        $this->assertSame(['400', '600', '700', '800'], $this->requestedWeights());
    }

    public function testWithoutAThemeListTheDefaultApplies(): void
    {
        $result = $this->bridge()->import(self::THEME, 'Inter');

        $this->assertSame(['400', '700'], $result['weights']);
    }

    public function testExtraWeightIsAddedAndSorted(): void
    {
        $result = $this->bridge()->import(self::THEME, 'Inter', ['800']);

        $this->assertSame(['400', '700', '800'], $result['weights']);
    }

    public function testAnExtraWeightAlreadyInTheListIsNotRequestedTwice(): void
    {
        $this->bridge()->import(self::THEME, 'Inter', ['700']);

        $this->assertSame(['400', '700'], $this->requestedWeights());
    }

    /**
     * A family that was imported before must only be topped up with what it is
     * missing, not downloaded again.
     */
    public function testWeightsAlreadyPresentAreSkipped(): void
    {
        $this->fs->mkdir($this->tmp.'/layout/custom/scss/base');
        file_put_contents(
            $this->tmp.'/layout/custom/scss/base/_fonts.scss',
            "@font-face{font-family:'Inter';font-style:normal;font-weight:400;src:url('../fonts/inter-400.woff2') format('woff2');}\n",
        );

        $result = $this->bridge()->import(self::THEME, 'Inter', ['800']);

        $this->assertSame(['700', '800'], $this->requestedWeights(), 'Der vorhandene Schnitt 400 wurde erneut geladen.');
        $this->assertSame(['400', '700', '800'], $result['weights'], 'Der vorhandene Schnitt fehlt im Ergebnis.');
    }

    /**
     * Lora has no 800. A weight the family does not offer is skipped silently; the
     * import only fails when not a single weight could be fetched.
     */
    public function testAWeightTheFamilyDoesNotOfferIsSkipped(): void
    {
        $this->unavailable = ['800'];

        $result = $this->bridge()->import(self::THEME, 'Lora', ['800']);

        $this->assertSame(['400', '700'], $result['weights']);
    }

    public function testImportFailsOnlyWhenNoWeightCouldBeFetched(): void
    {
        $this->downloadsFail = true;

        $this->expectException(\RuntimeException::class);

        $this->bridge()->import(self::THEME, 'Lora');
    }

    public function testReturnShapeIsUnchanged(): void
    {
        $result = $this->bridge()->import(self::THEME, 'Inter', ['800']);

        $this->assertSame(['family', 'value', 'weights', 'faces'], array_keys($result));
        $this->assertSame('Inter', $result['family']);
        $this->assertStringStartsWith('"Inter", ', $result['value']);
        $this->assertSame(['weight', 'path'], array_keys($result['faces'][0]));
    }

    // ----------------------------------------------------------- Helfer

    /**
     * @param array<string, mixed> $tokens
     */
    private function writeThemeTokens(array $tokens): void
    {
        file_put_contents($this->tmp.'/layout/'.self::THEME.'/tokens.json', (string) json_encode($tokens));
    }

    private function bridge(): GoogleFontBridge
    {
        $this->googleFonts
            ->method('downloadFontFiles')
            ->willReturnCallback(
                function (string $family, string $weight): array {
                    $this->requested[] = $weight;

                    if ($this->downloadsFail || \in_array($weight, $this->unavailable, true)) {
                        throw new \RuntimeException('Variante nicht verfügbar.');
                    }

                    $slug = strtolower(str_replace(' ', '-', $family));

                    return ['files' => [[
                        'filename' => $slug.'-'.$weight.'.woff2',
                        'content' => 'woff2-bytes',
                        'format' => 'woff2',
                    ]]];
                },
            )
        ;

        return new GoogleFontBridge(
            $this->googleFonts,
            $this->fileManager,
            new ThemeScssCompiler($this->fileManager, $this->tmp, $this->fs),
            new TokenRegistry($this->fileManager),
        );
    }

    /**
     * @return list<string>
     */
    private function requestedWeights(): array
    {
        return $this->requested;
    }
}
