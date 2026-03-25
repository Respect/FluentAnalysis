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
use Respect\FluentAnalysis\CacheGenerator;
use Respect\FluentAnalysis\MethodMapBuilder;
use Respect\FluentAnalysis\Test\Stubs\AssertingBuilder;
use Respect\FluentAnalysis\Test\Stubs\ChildBuilder;
use Respect\FluentAnalysis\Test\Stubs\FakeDiscovery;
use Respect\FluentAnalysis\Test\Stubs\Nodes\Cors;
use Respect\FluentAnalysis\Test\Stubs\Nodes\IntNode;
use Respect\FluentAnalysis\Test\Stubs\Nodes\NumericNode;
use Respect\FluentAnalysis\Test\Stubs\Nodes\RateLimit;
use Respect\FluentAnalysis\Test\Stubs\NonFluentBuilder;
use Respect\FluentAnalysis\Test\Stubs\TestBuilder;

use function strpos;

use const PHP_EOL;

#[CoversClass(CacheGenerator::class)]
final class CacheGeneratorTest extends TestCase
{
    #[Test]
    public function generateProducesNeonWithMethodMap(): void
    {
        $generator = $this->createGenerator([Cors::class, RateLimit::class]);

        $neon = $generator->generate([TestBuilder::class]);

        self::assertStringContainsString('parameters:', $neon);
        self::assertStringContainsString('fluent:', $neon);
        self::assertStringContainsString('methods:', $neon);
        self::assertStringContainsString(TestBuilder::class . ':', $neon);
        self::assertStringContainsString('cors: ' . Cors::class, $neon);
        self::assertStringContainsString('rateLimit: ' . RateLimit::class, $neon);
    }

    #[Test]
    public function generateReturnsEmptyMethodsWhenNoBuilders(): void
    {
        $generator = $this->createGenerator([]);

        $neon = $generator->generate([]);

        self::assertSame(
            'parameters:' . PHP_EOL
                . "\t" . 'fluent:' . PHP_EOL
                . "\t\t" . 'methods: []' . PHP_EOL
                . "\t\t" . 'assurances: []' . PHP_EOL
                . "\t\t" . 'assertions: []' . PHP_EOL,
            $neon,
        );
    }

    #[Test]
    public function generateSkipsClassWithoutFluentNamespaceAttribute(): void
    {
        $generator = $this->createGenerator([Cors::class]);

        $neon = $generator->generate(['stdClass']);

        self::assertSame(
            'parameters:' . PHP_EOL
                . "\t" . 'fluent:' . PHP_EOL
                . "\t\t" . 'methods: []' . PHP_EOL
                . "\t\t" . 'assurances: []' . PHP_EOL
                . "\t\t" . 'assertions: []' . PHP_EOL,
            $neon,
        );
    }

    #[Test]
    public function generateSkipsBuilderWithEmptyMap(): void
    {
        $generator = $this->createGenerator([]);

        $neon = $generator->generate([TestBuilder::class]);

        self::assertSame(
            'parameters:' . PHP_EOL
                . "\t" . 'fluent:' . PHP_EOL
                . "\t\t" . 'methods: []' . PHP_EOL
                . "\t\t" . 'assurances: []' . PHP_EOL
                . "\t\t" . 'assertions: []' . PHP_EOL,
            $neon,
        );
    }

    #[Test]
    public function generateSortsMethodsAlphabetically(): void
    {
        $generator = $this->createGenerator([RateLimit::class, Cors::class]);

        $neon = $generator->generate([TestBuilder::class]);

        $corsPos = strpos($neon, 'cors:');
        $rateLimitPos = strpos($neon, 'rateLimit:');

        self::assertNotFalse($corsPos);
        self::assertNotFalse($rateLimitPos);
        self::assertLessThan($rateLimitPos, $corsPos, 'Methods should be sorted alphabetically');
    }

    #[Test]
    public function generateResolvesAttributeFromParentClass(): void
    {
        $generator = $this->createGenerator([Cors::class]);

        // ChildBuilder has no #[FluentNamespace] itself — inherited from TestBuilder
        $neon = $generator->generate([ChildBuilder::class]);

        self::assertStringContainsString(ChildBuilder::class . ':', $neon);
        self::assertStringContainsString('cors: ' . Cors::class, $neon);
    }

    #[Test]
    public function generateIncludesAssuranceSection(): void
    {
        $generator = $this->createGenerator([IntNode::class, NumericNode::class]);

        $neon = $generator->generate([AssertingBuilder::class]);

        self::assertStringContainsString('assurances:', $neon);
        self::assertStringContainsString(AssertingBuilder::class . ':', $neon);
        self::assertStringContainsString('intNode: { type: int }', $neon);
        self::assertStringContainsString('numericNode: { type: int|float|numeric-string }', $neon);
    }

    #[Test]
    public function generateIncludesAssertionSection(): void
    {
        $generator = $this->createGenerator([IntNode::class]);

        $neon = $generator->generate([AssertingBuilder::class]);

        self::assertStringContainsString('assertions:', $neon);
        self::assertStringContainsString(AssertingBuilder::class . ': [doAssert, isOk]', $neon);
    }

    #[Test]
    public function generateIncludesServiceSectionForNonFluentBuilder(): void
    {
        $generator = $this->createGenerator([Cors::class]);

        $neon = $generator->generate([NonFluentBuilder::class]);

        self::assertStringContainsString('services:', $neon);
        self::assertStringContainsString('FluentDynamicReturnTypeExtension', $neon);
        self::assertStringContainsString('FluentTypeSpecifyingExtension', $neon);
        self::assertStringContainsString('targetClass: ' . NonFluentBuilder::class, $neon);
    }

    #[Test]
    public function generateSkipsServiceSectionForFluentBuilderSubclass(): void
    {
        $generator = $this->createGenerator([Cors::class]);

        $neon = $generator->generate([TestBuilder::class]);

        self::assertStringNotContainsString('services:', $neon);
    }

    /** @param list<class-string> $discoveredClasses */
    private function createGenerator(array $discoveredClasses): CacheGenerator
    {
        $discovery = new FakeDiscovery(['Respect\\FluentAnalysis\\Test\\Stubs\\Nodes' => $discoveredClasses]);

        return new CacheGenerator(new MethodMapBuilder($discovery));
    }
}
