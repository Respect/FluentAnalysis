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
use ReflectionProperty;
use Respect\FluentAnalysis\MethodMapBuilder;
use Respect\FluentAnalysis\MethodMapFactory;
use Respect\FluentAnalysis\Test\Stubs\ExtraNodes\Custom;
use Respect\FluentAnalysis\Test\Stubs\ExtraNodes\StringNode;
use Respect\FluentAnalysis\Test\Stubs\FakeDiscovery;
use Respect\FluentAnalysis\Test\Stubs\Nodes\Cors;
use Respect\FluentAnalysis\Test\Stubs\Nodes\Not;
use Respect\FluentAnalysis\Test\Stubs\Nodes\RateLimit;
use Respect\FluentAnalysis\Test\Stubs\TestBuilder;
use Respect\FluentAnalysis\Test\Stubs\TestComposingBuilder;

use function assert;
use function is_array;

#[CoversClass(MethodMapFactory::class)]
final class MethodMapFactoryTest extends TestCase
{
    private const string NAMESPACE = 'Respect\\FluentAnalysis\\Test\\Stubs\\Nodes';
    private const string EXTRA_NAMESPACE = 'Respect\\FluentAnalysis\\Test\\Stubs\\ExtraNodes';

    #[Test]
    public function buildsMethodMapFromBuilderAttribute(): void
    {
        $factory = new MethodMapFactory(
            [['builder' => TestBuilder::class]],
            $this->builderWith([self::NAMESPACE => [Cors::class, RateLimit::class]]),
        );

        $methods = $this->extractMethods($factory->createMethodMap());

        self::assertSame(Cors::class, $methods[TestBuilder::class]['cors']);
        self::assertSame(RateLimit::class, $methods[TestBuilder::class]['rateLimit']);
    }

    #[Test]
    public function mergesExtraNamespaceFromEntry(): void
    {
        $factory = new MethodMapFactory(
            [
                ['builder' => TestBuilder::class],
                ['builder' => TestBuilder::class, 'namespace' => self::EXTRA_NAMESPACE],
            ],
            $this->builderWith([
                self::NAMESPACE => [Cors::class],
                self::EXTRA_NAMESPACE => [Custom::class, StringNode::class],
            ]),
        );

        $methods = $this->extractMethods($factory->createMethodMap());

        self::assertSame(Cors::class, $methods[TestBuilder::class]['cors']);
        self::assertSame(Custom::class, $methods[TestBuilder::class]['custom']);
        self::assertSame(StringNode::class, $methods[TestBuilder::class]['stringNode']);
    }

    #[Test]
    public function composedPrefixesWorkWithExtraNamespaces(): void
    {
        $factory = new MethodMapFactory(
            [
                ['builder' => TestComposingBuilder::class],
                ['builder' => TestComposingBuilder::class, 'namespace' => self::EXTRA_NAMESPACE],
            ],
            $this->builderWith([
                self::NAMESPACE => [Cors::class, Not::class],
                self::EXTRA_NAMESPACE => [Custom::class, StringNode::class],
            ]),
        );

        $methods = $this->extractMethods($factory->createMethodMap());

        self::assertSame(Custom::class, $methods[TestComposingBuilder::class]['custom']);
        self::assertSame(Custom::class, $methods[TestComposingBuilder::class]['notCustom']);
        self::assertSame(StringNode::class, $methods[TestComposingBuilder::class]['notStringNode']);
    }

    #[Test]
    public function buildsAssuranceMapFromAttribute(): void
    {
        $factory = new MethodMapFactory(
            [
                ['builder' => TestBuilder::class],
                ['builder' => TestBuilder::class, 'namespace' => self::EXTRA_NAMESPACE],
            ],
            $this->builderWith([
                self::NAMESPACE => [],
                self::EXTRA_NAMESPACE => [StringNode::class],
            ]),
        );

        $assurances = new ReflectionProperty($factory->createAssuranceMap(), 'assurances');
        $value = $assurances->getValue($factory->createAssuranceMap());
        assert(is_array($value));

        self::assertSame(['type' => 'string'], $value[TestBuilder::class]['stringNode']);
    }

    #[Test]
    public function ignoresUnknownBuilderClass(): void
    {
        $factory = new MethodMapFactory(
            [['builder' => 'NonExistent\\Builder']],
        );

        self::assertSame([], $this->extractMethods($factory->createMethodMap()));
    }

    #[Test]
    public function emptyBuildersProducesEmptyMaps(): void
    {
        $factory = new MethodMapFactory([]);

        self::assertSame([], $this->extractMethods($factory->createMethodMap()));
    }

    #[Test]
    public function duplicateNamespaceEntriesAreDeduped(): void
    {
        $factory = new MethodMapFactory(
            [
                ['builder' => TestBuilder::class],
                ['builder' => TestBuilder::class, 'namespace' => self::EXTRA_NAMESPACE],
                ['builder' => TestBuilder::class, 'namespace' => self::EXTRA_NAMESPACE],
            ],
            $this->builderWith([
                self::NAMESPACE => [Cors::class],
                self::EXTRA_NAMESPACE => [Custom::class],
            ]),
        );

        $methods = $this->extractMethods($factory->createMethodMap());

        // Custom appears once, not duplicated
        self::assertSame(Custom::class, $methods[TestBuilder::class]['custom']);
    }

    /** @param array<string, list<class-string>> $map */
    private function builderWith(array $map): MethodMapBuilder
    {
        return new MethodMapBuilder(new FakeDiscovery($map));
    }

    /** @return array<string, array<string, string>> */
    private function extractMethods(object $map): array
    {
        $reflection = new ReflectionProperty($map, 'methods');
        $value = $reflection->getValue($map);
        assert(is_array($value));

        return $value;
    }
}
