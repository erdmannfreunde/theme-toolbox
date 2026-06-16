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
 * Pre-downloads a showcase set of self-hosted fonts so the public/demo editor can
 * offer them instantly without a per-visitor server write (§4.3). Run once as a
 * deploy step on the demo installation.
 */
#[AsCommand(
    name: 'theme-toolbox:editor:preload-demo-fonts',
    description: 'Download the showcase font set (self-hosted) for the public/demo editor.',
)]
class PreloadDemoFontsCommand extends Command
{
    /** Default showcase set — popular, distinct families covering the demo. */
    private const DEFAULT_FAMILIES = ['Inter', 'Source Sans 3', 'Space Grotesk', 'Merriweather', 'Roboto'];

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
            ->addOption('families', null, InputOption::VALUE_REQUIRED, 'Comma-separated font families to preload')
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
            ? array_filter(array_map('trim', explode(',', $familiesOption)))
            : self::DEFAULT_FAMILIES;

        $io->title(sprintf('Preloading %d showcase fonts for theme "%s"', \count($families), $theme));

        $failed = 0;

        foreach ($families as $family) {
            try {
                $result = $this->fontBridge->import($theme, $family);
                $io->writeln(sprintf(' <info>✓</info> %s (weights: %s)', $family, implode(', ', $result['weights'])));
            } catch (\RuntimeException $e) {
                ++$failed;
                $io->writeln(sprintf(' <error>✗</error> %s — %s', $family, $e->getMessage()));
            }
        }

        if ($failed > 0) {
            $io->warning(sprintf('%d of %d fonts could not be downloaded.', $failed, \count($families)));

            return Command::FAILURE;
        }

        $io->success('Showcase fonts are self-hosted and ready for the public-mode editor.');

        return Command::SUCCESS;
    }
}
