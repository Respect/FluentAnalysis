<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\FluentAnalysis\Commands\GenerateCommand;
use Respect\FluentAnalysis\ConfigGenerator;
use Respect\FluentAnalysis\Test\Stubs\TestBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function is_file;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(GenerateCommand::class)]
final class GenerateCommandTest extends TestCase
{
    #[Test]
    public function executeWritesFileWhenBuildersFound(): void
    {
        $outputFile = tempnam(sys_get_temp_dir(), 'fluent-test-') . '.neon';

        try {
            $tester = $this->runCommand([TestBuilder::class], $outputFile);

            self::assertSame(Command::SUCCESS, $tester->getStatusCode());
            self::assertStringContainsString('Generated', $tester->getDisplay());
            self::assertFileExists($outputFile);
        } finally {
            if (is_file($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    #[Test]
    public function executeReportsNoClassesWhenScanFindsNone(): void
    {
        $outputFile = tempnam(sys_get_temp_dir(), 'fluent-test-') . '.neon';

        try {
            $tester = $this->runCommand([], $outputFile);

            self::assertSame(Command::SUCCESS, $tester->getStatusCode());
            self::assertStringContainsString('No classes', $tester->getDisplay());
        } finally {
            if (is_file($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    #[Test]
    public function executeReportsNoChangesWhenFileMatches(): void
    {
        $outputFile = tempnam(sys_get_temp_dir(), 'fluent-test-') . '.neon';

        try {
            $this->runCommand([TestBuilder::class], $outputFile);

            $tester = $this->runCommand([TestBuilder::class], $outputFile);

            self::assertSame(Command::SUCCESS, $tester->getStatusCode());
            self::assertStringContainsString('No changes', $tester->getDisplay());
        } finally {
            if (is_file($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    /** @param list<class-string> $scannedClasses */
    private function runCommand(array $scannedClasses, string $outputFile): CommandTester
    {
        $scanner = new FakeScanner($scannedClasses);
        $generator = new ConfigGenerator();

        $command = new GenerateCommand($scanner, $generator);
        $tester = new CommandTester($command);
        $tester->execute(['--output' => $outputFile]);

        return $tester;
    }
}
