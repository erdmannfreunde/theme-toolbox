<?php

declare(strict_types=1);

/*
 * This file is part of erdmannfreunde/theme-toolbox.
 *
 * (c) Erdmann & Freunde <https://erdmann-freunde.de>
 *
 * @license LGPL-3.0-or-later
 */

namespace ErdmannFreunde\ThemeToolboxBundle\Editor\Command;

use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\GoogleFontBridge;
use ErdmannFreunde\ThemeToolboxBundle\Editor\Service\TokenRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Pre-downloads the fonts the theme's presets ask for, self-hosted, so the
 * public/demo editor shows them instead of a fallback (§4.3). That editor never
 * persists, so it never downloads anything on its own. Run once as a deploy step
 * on the demo installation, and again whenever a preset changes its fonts.
 */
#[AsCommand(
    name: 'theme-toolbox:editor:preload-demo-fonts',
    description: 'Download the fonts used by the theme presets (self-hosted) for the public/demo editor.',
)]
class PreloadDemoFontsCommand extends Command
{
    public function __construct(
        private readonly GoogleFontBridge $fontBridge,
        private readonly TokenRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('theme', InputArgument::OPTIONAL, 'Theme directory name (defaults to the active theme)')
            ->addOption('families', null, InputOption::VALUE_REQUIRED, 'Comma-separated font families to preload instead of the ones the presets use')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $theme = (string) ($input->getArgument('theme') ?: $this->registry->getActiveTheme());

        if ('' === $theme) {
            $io->error('No theme found. Pass a theme directory name as argument.');

            return Command::FAILURE;
        }

        $familiesOption = (string) $input->getOption('families');
        $families = '' !== $familiesOption
            ? array_values(array_filter(array_map('trim', explode(',', $familiesOption))))
            : $this->registry->getPresetFontFamilies($theme);

        if ([] === $families) {
            $io->success(\sprintf('No preset of theme "%s" asks for a downloadable font. Nothing to preload.', $theme));

            return Command::SUCCESS;
        }

        $io->title(\sprintf('Preloading %d fonts for theme "%s"', \count($families), $theme));

        $failed = 0;

        foreach ($families as $family) {
            try {
                $result = $this->fontBridge->import($theme, $family);
                $io->writeln(\sprintf(' <info>✓</info> %s (weights: %s)', $family, implode(', ', $result['weights'])));
            } catch (\RuntimeException $e) {
                ++$failed;
                $io->writeln(\sprintf(' <error>✗</error> %s — %s', $family, $e->getMessage()));
            }
        }

        if ($failed > 0) {
            $io->warning(\sprintf('%d of %d fonts could not be downloaded.', $failed, \count($families)));

            return Command::FAILURE;
        }

        $io->success('Preset fonts are self-hosted and ready for the public-mode editor.');

        return Command::SUCCESS;
    }
}
