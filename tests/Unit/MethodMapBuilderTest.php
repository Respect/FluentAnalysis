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
use Respect\FluentAnalysis\Test\Stubs\AssertingBuilder;
use Respect\FluentAnalysis\Test\Stubs\ChildAssertingBuilder;
use Respect\FluentAnalysis\Test\Stubs\FakeDiscovery;
use Respect\FluentAnalysis\Test\Stubs\Nodes\Cors;
use Respect\FluentAnalysis\Test\Stubs\Nodes\ElementsNode;
use Respect\FluentAnalysis\Test\Stubs\Nodes\InstanceNode;
use Respect\FluentAnalysis\Test\Stubs\Nodes\IntersectNode;
use Respect\FluentAnalysis\Test\Stubs\Nodes\IntNode;
use Respect\FluentAnalysis\Test\Stubs\Nodes\Key;
use Respect\FluentAnalysis\Test\Stubs\Nodes\MemberNode;
use Respect\FluentAnalysis\Test\Stubs\Nodes\Not;
use Respect\FluentAnalysis\Test\Stubs\Nodes\OptInOnly;
use Respect\FluentAnalysis\Test\Stubs\Nodes\RateLimit;
use Respect\FluentAnalysis\Test\Stubs\Nodes\UnionNode;
use Respect\FluentAnalysis\Test\Stubs\Nodes\ValueNode;

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

    #[Test]
    public function buildAssurancesExtractsTypeAttribute(): void
    {
        $assurances = $this->buildAssurancesWith(
            [IntNode::class],
            new FluentNamespace(new NamespaceLookup(new Ucfirst(), null, self::NAMESPACE)),
        );

        self::assertSame(['type' => 'int'], $assurances['intNode']);
    }

    #[Test]
    public function buildAssurancesExtractsParameterAttribute(): void
    {
        $assurances = $this->buildAssurancesWith(
            [InstanceNode::class],
            new FluentNamespace(new NamespaceLookup(new Ucfirst(), null, self::NAMESPACE)),
        );

        self::assertSame(['parameterIndex' => 0], $assurances['instanceNode']);
    }

    #[Test]
    public function buildAssurancesExtractsFromAttribute(): void
    {
        $assurances = $this->buildAssurancesWith(
            [ValueNode::class, MemberNode::class, ElementsNode::class],
            new FluentNamespace(new NamespaceLookup(new Ucfirst(), null, self::NAMESPACE)),
        );

        self::assertSame('value', $assurances['valueNode']['from']);
        self::assertSame('member', $assurances['memberNode']['from']);
        self::assertSame('elements', $assurances['elementsNode']['from']);
    }

    #[Test]
    public function buildAssurancesExtractsComposeAttribute(): void
    {
        $assurances = $this->buildAssurancesWith(
            [UnionNode::class, IntersectNode::class],
            new FluentNamespace(new NamespaceLookup(new Ucfirst(), null, self::NAMESPACE)),
        );

        self::assertSame('union', $assurances['unionNode']['compose']);
        self::assertSame('intersect', $assurances['intersectNode']['compose']);
    }

    #[Test]
    public function buildAssurancesReturnsEmptyForNoAssurances(): void
    {
        $assurances = $this->buildAssurancesWith(
            [Cors::class],
            new FluentNamespace(new NamespaceLookup(new Ucfirst(), null, self::NAMESPACE)),
        );

        self::assertSame([], $assurances);
    }

    #[Test]
    public function buildAssurancesGeneratesComposedAssurances(): void
    {
        $assurances = $this->buildAssurancesWith(
            [IntNode::class, Not::class],
            new FluentNamespace(new ComposingLookup(new NamespaceLookup(new Ucfirst(), null, self::NAMESPACE))),
        );

        self::assertArrayHasKey('intNode', $assurances);
        self::assertArrayHasKey('notIntNode', $assurances);
    }

    #[Test]
    public function buildAssertionsExtractsAssuranceAssertionAttribute(): void
    {
        $builder = new MethodMapBuilder(new FakeDiscovery([]));

        $assertions = $builder->buildAssertions(AssertingBuilder::class);

        self::assertSame(['doAssert', 'isOk'], $assertions);
    }

    #[Test]
    public function buildAssertionsIncludesInheritedMethods(): void
    {
        $builder = new MethodMapBuilder(new FakeDiscovery([]));

        $assertions = $builder->buildAssertions(ChildAssertingBuilder::class);

        self::assertSame(['doAssert', 'isOk'], $assertions);
    }

    #[Test]
    public function buildProducesPrefixParameterFormat(): void
    {
        $map = $this->buildWith(
            [Cors::class, Key::class],
            new FluentNamespace(new ComposingLookup(new NamespaceLookup(new Ucfirst(), null, self::NAMESPACE))),
        );

        self::assertSame(Cors::class . ':' . Key::class, $map['keyCors']);
    }

    #[Test]
    public function buildAssurancesIncludesWrapperModifier(): void
    {
        $assurances = $this->buildAssurancesWith(
            [IntNode::class, Not::class],
            new FluentNamespace(new ComposingLookup(new NamespaceLookup(new Ucfirst(), null, self::NAMESPACE))),
        );

        // Not has #[Assurance(modifier: Exclude)], so composed 'notIntNode' inherits modifier
        self::assertArrayHasKey('notIntNode', $assurances);
        self::assertSame('exclude', $assurances['notIntNode']['wrapperModifier']);
    }

    #[Test]
    public function buildAssertionsReturnsEmptyForNoAttribute(): void
    {
        $builder = new MethodMapBuilder(new FakeDiscovery([]));

        $assertions = $builder->buildAssertions(Cors::class);

        self::assertSame([], $assertions);
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

    /**
     * @param list<class-string> $classes
     *
     * @return array<string, array<string, string>>
     */
    private function buildAssurancesWith(array $classes, FluentNamespace $attribute): array
    {
        $builder = new MethodMapBuilder(new FakeDiscovery([self::NAMESPACE => $classes]));

        return $builder->buildAssurances($attribute);
    }
}
