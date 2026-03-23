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
use Respect\FluentAnalysis\BuilderClassScanner;
use Respect\FluentAnalysis\Test\Stubs\TestBuilder;

use function file_put_contents;
use function json_encode;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

#[CoversClass(BuilderClassScanner::class)]
final class BuilderClassScannerTest extends TestCase
{
    #[Test]
    public function scanFindsBuilderClassesInProject(): void
    {
        $scanner = new BuilderClassScanner();

        // Scan the FluentAnalysis project itself — TestBuilder has #[FluentNamespace]
        $classes = $scanner->scan(__DIR__ . '/../../composer.json');

        self::assertContains(TestBuilder::class, $classes);
    }

    #[Test]
    public function scanReturnsEmptyForNonExistentComposerJson(): void
    {
        $scanner = new BuilderClassScanner();

        self::assertSame([], $scanner->scan('/nonexistent/composer.json'));
    }

    #[Test]
    public function scanReturnsEmptyForInvalidJson(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'fluent-test-');
        file_put_contents($file, 'not json');

        try {
            $scanner = new BuilderClassScanner();
            self::assertSame([], $scanner->scan($file));
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function scanReturnsEmptyForComposerJsonWithoutPsr4(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'fluent-test-');
        file_put_contents($file, json_encode(['name' => 'test/test']));

        try {
            $scanner = new BuilderClassScanner();
            self::assertSame([], $scanner->scan($file));
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function scanSkipsNonExistentDirectories(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'fluent-test-');
        file_put_contents($file, json_encode([
            'autoload' => [
                'psr-4' => ['Fake\\' => '/nonexistent/directory/'],
            ],
        ]));

        try {
            $scanner = new BuilderClassScanner();
            self::assertSame([], $scanner->scan($file));
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function scanHandlesStringDirInsteadOfArray(): void
    {
        $dir = sys_get_temp_dir() . '/fluent-test-empty-' . uniqid();
        mkdir($dir);
        $file = tempnam(sys_get_temp_dir(), 'fluent-test-');
        file_put_contents($file, json_encode([
            'autoload' => [
                'psr-4' => ['Fake\\' => $dir],
            ],
        ]));

        try {
            $scanner = new BuilderClassScanner();
            // Empty directory — no classes found, but no crash
            self::assertSame([], $scanner->scan($file));
        } finally {
            unlink($file);
            rmdir($dir);
        }
    }
}
