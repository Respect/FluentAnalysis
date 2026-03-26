<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Commands;

use Respect\FluentAnalysis\BuilderClassScanner;
use Respect\FluentAnalysis\ConfigGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function assert;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function is_file;
use function is_string;
use function sprintf;

#[AsCommand(
    name: 'generate',
    description: 'Generate fluent.neon config for PHPStan',
)]
final class GenerateCommand extends Command
{
    public function __construct(
        private readonly BuilderClassScanner $scanner = new BuilderClassScanner(),
        private readonly ConfigGenerator $generator = new ConfigGenerator(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'output',
            'o',
            InputOption::VALUE_REQUIRED,
            'Output file path',
            'fluent.neon',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputFile = $input->getOption('output');
        assert(is_string($outputFile));

        $builderClasses = $this->scanner->scan(getcwd() . '/composer.json');

        if ($builderClasses === []) {
            $output->writeln('<comment>No classes with #[FluentNamespace] attribute found.</comment>');

            return Command::SUCCESS;
        }

        $content = $this->generator->generate($builderClasses);
        $existing = is_file($outputFile) ? (file_get_contents($outputFile) ?: '') : '';

        if ($content === $existing) {
            $output->writeln(sprintf('<info>No changes needed.</info>'));

            return Command::SUCCESS;
        }

        file_put_contents($outputFile, $content);
        $output->writeln(sprintf('<info>Generated %s</info>', $outputFile));

        return Command::SUCCESS;
    }
}
