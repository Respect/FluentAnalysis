<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Unit;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Respect\FluentAnalysis\MethodMap;
use Respect\FluentAnalysis\Test\Fixtures\TestBuilder;
use Respect\FluentAnalysis\Test\Stubs\Nodes\Cors;

#[CoversClass(MethodMap::class)]
final class MethodMapTest extends PHPStanTestCase
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
    public function resolveFindsExactClassMatch(): void
    {
        $map = new MethodMap([
            TestBuilder::class => ['cors' => Cors::class],
        ]);

        $class = $this->getReflectionProvider()->getClass(TestBuilder::class);

        self::assertSame(Cors::class, $map->resolve($class, 'cors'));
        self::assertTrue($map->has($class, 'cors'));
    }

    #[Test]
    public function resolveReturnsNullForUnknownMethod(): void
    {
        $map = new MethodMap([
            TestBuilder::class => ['cors' => Cors::class],
        ]);

        $class = $this->getReflectionProvider()->getClass(TestBuilder::class);

        self::assertNull($map->resolve($class, 'unknown'));
        self::assertFalse($map->has($class, 'unknown'));
    }

    #[Test]
    public function resolveFallsBackToParentClass(): void
    {
        $map = new MethodMap([
            'Respect\\Fluent\\Builders\\Append' => ['cors' => Cors::class],
        ]);

        // TestBuilder extends Append
        $class = $this->getReflectionProvider()->getClass(TestBuilder::class);

        self::assertSame(Cors::class, $map->resolve($class, 'cors'));
    }

    #[Test]
    public function resolveReturnsNullForUnregisteredClass(): void
    {
        $map = new MethodMap([
            TestBuilder::class => ['cors' => Cors::class],
        ]);

        $class = $this->getReflectionProvider()->getClass('stdClass');

        self::assertNull($map->resolve($class, 'cors'));
        self::assertFalse($map->has($class, 'cors'));
    }

    private function getReflectionProvider(): ReflectionProvider
    {
        return self::getContainer()->getByType(ReflectionProvider::class);
    }
}
