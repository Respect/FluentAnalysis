<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Unit;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Respect\FluentAnalysis\AssuranceMap;
use Respect\FluentAnalysis\Test\Fixtures\TestBuilder;

#[CoversClass(AssuranceMap::class)]
final class AssuranceMapTest extends PHPStanTestCase
{
    /** @return list<string> */
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/../../extension.neon',
            __DIR__ . '/../fixtures/fluent.neon',
        ];
    }

    #[Test]
    public function resolveAssuranceFindsExactClassMatch(): void
    {
        $map = new AssuranceMap(
            assurances: [TestBuilder::class => ['intNode' => ['type' => 'int']]],
        );

        $class = $this->reflect(TestBuilder::class);

        self::assertSame(['type' => 'int'], $map->resolveAssurance($class, 'intNode'));
    }

    #[Test]
    public function resolveAssuranceReturnsNullForUnknownMethod(): void
    {
        $map = new AssuranceMap(
            assurances: [TestBuilder::class => ['intNode' => ['type' => 'int']]],
        );

        $class = $this->reflect(TestBuilder::class);

        self::assertNull($map->resolveAssurance($class, 'unknown'));
    }

    #[Test]
    public function resolveAssuranceFallsBackToParentClass(): void
    {
        $map = new AssuranceMap(
            assurances: ['Respect\\Fluent\\Builders\\Append' => ['intNode' => ['type' => 'int']]],
        );

        // TestBuilder extends Append
        $class = $this->reflect(TestBuilder::class);

        self::assertSame(['type' => 'int'], $map->resolveAssurance($class, 'intNode'));
    }

    #[Test]
    public function resolveAssuranceReturnsNullForUnregisteredClass(): void
    {
        $map = new AssuranceMap(
            assurances: [TestBuilder::class => ['intNode' => ['type' => 'int']]],
        );

        $class = $this->reflect('stdClass');

        self::assertNull($map->resolveAssurance($class, 'intNode'));
    }

    #[Test]
    public function isAssertionMethodFindsExactClassMatch(): void
    {
        $map = new AssuranceMap(
            assertions: [TestBuilder::class => ['doAssert', 'isOk']],
        );

        $class = $this->reflect(TestBuilder::class);

        self::assertTrue($map->isAssertionMethod($class, 'doAssert'));
        self::assertTrue($map->isAssertionMethod($class, 'isOk'));
        self::assertFalse($map->isAssertionMethod($class, 'intNode'));
    }

    #[Test]
    public function isAssertionMethodFallsBackToParentClass(): void
    {
        $map = new AssuranceMap(
            assertions: ['Respect\\Fluent\\Builders\\Append' => ['doAssert']],
        );

        $class = $this->reflect(TestBuilder::class);

        self::assertTrue($map->isAssertionMethod($class, 'doAssert'));
    }

    #[Test]
    public function isAssertionMethodReturnsFalseForUnregisteredClass(): void
    {
        $map = new AssuranceMap(
            assertions: [TestBuilder::class => ['doAssert']],
        );

        $class = $this->reflect('stdClass');

        self::assertFalse($map->isAssertionMethod($class, 'doAssert'));
    }

    #[Test]
    public function emptyMapReturnsDefaults(): void
    {
        $map = new AssuranceMap();

        $class = $this->reflect(TestBuilder::class);

        self::assertNull($map->resolveAssurance($class, 'anything'));
        self::assertFalse($map->isAssertionMethod($class, 'anything'));
    }

    private function reflect(string $class): ClassReflection
    {
        return self::getContainer()
            ->getByType(ReflectionProvider::class)
            ->getClass($class);
    }
}
