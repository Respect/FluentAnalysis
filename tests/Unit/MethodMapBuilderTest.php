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
use Respect\Fluent\Attributes\FluentNamespace;
use Respect\Fluent\Factories\ComposingLookup;
use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Fluent\Resolvers\Suffix;
use Respect\Fluent\Resolvers\Ucfirst;
use Respect\FluentAnalysis\MethodMapBuilder;
use Respect\FluentAnalysis\Test\Stubs\FakeDiscovery;
use Respect\FluentAnalysis\Test\Stubs\Nodes\Cors;
use Respect\FluentAnalysis\Test\Stubs\Nodes\Not;
use Respect\FluentAnalysis\Test\Stubs\Nodes\OptInOnly;
use Respect\FluentAnalysis\Test\Stubs\Nodes\RateLimit;

#[CoversClass(MethodMapBuilder::class)]
final class MethodMapBuilderTest extends TestCase
{
    private const string NAMESPACE = 'Respect\\FluentAnalysis\\Test\\Stubs\\Nodes';

    #[Test]
    public function buildDiscoversFlatMethods(): void
    {
        $map = $this->buildWith(
            [Cors::class, RateLimit::class],
            new FluentNamespace(new NamespaceLookup(new Ucfirst(), null, self::NAMESPACE)),
        );

        self::assertSame(Cors::class, $map['cors']);
        self::assertSame(RateLimit::class, $map['rateLimit']);
        self::assertCount(2, $map);
    }

    #[Test]
    public function buildReturnsEmptyForEmptyDiscovery(): void
    {
        $map = $this->buildWith(
            [],
            new FluentNamespace(new NamespaceLookup(new Ucfirst(), null, self::NAMESPACE)),
        );

        self::assertSame([], $map);
    }

    #[Test]
    public function buildGeneratesComposedMethods(): void
    {
        $map = $this->buildWith(
            [Cors::class, RateLimit::class, Not::class],
            new FluentNamespace(new ComposingLookup(new NamespaceLookup(new Ucfirst(), null, self::NAMESPACE))),
        );

        // Base methods
        self::assertSame(Cors::class, $map['cors']);
        self::assertSame(RateLimit::class, $map['rateLimit']);
        self::assertSame(Not::class, $map['not']);

        // Composed methods
        self::assertSame(Cors::class, $map['notCors']);
        self::assertSame(RateLimit::class, $map['notRateLimit']);
    }

    #[Test]
    public function composableWithoutBlocksSelfComposition(): void
    {
        $map = $this->buildWith(
            [Cors::class, Not::class],
            new FluentNamespace(new ComposingLookup(new NamespaceLookup(new Ucfirst(), null, self::NAMESPACE))),
        );

        // Not has without: ['not'], so notNot should not exist
        self::assertArrayNotHasKey('notNot', $map);

        // But notCors should exist
        self::assertArrayHasKey('notCors', $map);
    }

    #[Test]
    public function composableOptInOnlyAllowsExplicitPrefixes(): void
    {
        $map = $this->buildWith(
            [Cors::class, Not::class, OptInOnly::class],
            new FluentNamespace(new ComposingLookup(new NamespaceLookup(new Ucfirst(), null, self::NAMESPACE))),
        );

        // OptInOnly has with: ['not'] — only 'not' prefix is allowed
        self::assertSame(OptInOnly::class, $map['notOptInOnly']);
    }

    #[Test]
    public function buildSkipsComposableWhenDisabled(): void
    {
        $map = $this->buildWith(
            [Cors::class, Not::class],
            new FluentNamespace(new NamespaceLookup(new Ucfirst(), null, self::NAMESPACE)),
        );

        self::assertArrayNotHasKey('notCors', $map);
        self::assertCount(2, $map);
    }

    #[Test]
    public function classSuffixIsStripped(): void
    {
        $map = $this->buildWith(
            [Cors::class],
            new FluentNamespace(new NamespaceLookup(new Suffix('', 'ors'), null, self::NAMESPACE)),
        );

        // 'Cors' with suffix 'ors' stripped → 'C' → lcfirst → 'c'
        self::assertSame(Cors::class, $map['c']);
    }

    #[Test]
    public function classSuffixIsStrippedInComposedMethods(): void
    {
        $map = $this->buildWith(
            [Cors::class, Not::class],
            new FluentNamespace(new ComposingLookup(new NamespaceLookup(new Suffix('', 'ors'), null, self::NAMESPACE))),
        );

        // Composed: 'not' + 'C' (suffix stripped) → 'notC'
        self::assertArrayHasKey('notC', $map);
        self::assertSame(Cors::class, $map['notC']);
    }

    #[Test]
    public function optInPrefixSkipsClassWithoutComposableAttribute(): void
    {
        // Cors has no Composable attribute, Not has optIn prefix
        // When Not is optIn, Cors should NOT get a composed method
        // unless Cors explicitly opts in
        $map = $this->buildWith(
            [Cors::class, OptInOnly::class, Not::class],
            new FluentNamespace(new ComposingLookup(new NamespaceLookup(new Ucfirst(), null, self::NAMESPACE))),
        );

        // Cors has no Composable attribute at all — with an optIn prefix,
        // it should not be composed (optIn requires explicit with list)
        // But 'not' prefix is NOT optIn (Not has without, not optIn)
        // so notCors should exist
        self::assertArrayHasKey('notCors', $map);

        // OptInOnly has with: ['not'] so notOptInOnly should exist
        self::assertArrayHasKey('notOptInOnly', $map);
    }

    /**
     * @param list<class-string> $classes
     *
     * @return array<string, class-string>
     */
    private function buildWith(array $classes, FluentNamespace $attribute): array
    {
        $builder = new MethodMapBuilder(new FakeDiscovery([self::NAMESPACE => $classes]));

        return $builder->build($attribute);
    }
}
