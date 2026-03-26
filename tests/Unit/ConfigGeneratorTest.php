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
use Respect\FluentAnalysis\ConfigGenerator;
use Respect\FluentAnalysis\Test\Stubs\NonFluentBuilder;
use Respect\FluentAnalysis\Test\Stubs\TestBuilder;

#[CoversClass(ConfigGenerator::class)]
final class ConfigGeneratorTest extends TestCase
{
    #[Test]
    public function generateListsBuildersInParameters(): void
    {
        $generator = new ConfigGenerator();

        $neon = $generator->generate([TestBuilder::class]);

        self::assertStringContainsString('parameters:', $neon);
        self::assertStringContainsString('builders:', $neon);
        self::assertStringContainsString('- builder: ' . TestBuilder::class, $neon);
    }

    #[Test]
    public function generateProducesEmptyBuildersForEmptyInput(): void
    {
        $generator = new ConfigGenerator();

        $neon = $generator->generate([]);

        self::assertStringContainsString('builders: []', $neon);
        self::assertStringNotContainsString('- builder:', $neon);
    }

    #[Test]
    public function generateIncludesServiceSectionForNonFluentBuilder(): void
    {
        $generator = new ConfigGenerator();

        $neon = $generator->generate([NonFluentBuilder::class]);

        self::assertStringContainsString('services:', $neon);
        self::assertStringContainsString('FluentDynamicReturnTypeExtension', $neon);
        self::assertStringContainsString('FluentTypeSpecifyingExtension', $neon);
        self::assertStringContainsString('targetClass: ' . NonFluentBuilder::class, $neon);
    }

    #[Test]
    public function generateSkipsServiceSectionForFluentBuilderSubclass(): void
    {
        $generator = new ConfigGenerator();

        $neon = $generator->generate([TestBuilder::class]);

        self::assertStringNotContainsString('services:', $neon);
    }

    #[Test]
    public function generateListsMultipleBuilders(): void
    {
        $generator = new ConfigGenerator();

        $neon = $generator->generate([TestBuilder::class, NonFluentBuilder::class]);

        self::assertStringContainsString('- builder: ' . TestBuilder::class, $neon);
        self::assertStringContainsString('- builder: ' . NonFluentBuilder::class, $neon);
    }
}
