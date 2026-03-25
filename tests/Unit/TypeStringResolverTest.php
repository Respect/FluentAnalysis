<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Unit;

use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\CallableType;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\IterableType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\ResourceType;
use PHPStan\Type\StringType;
use PHPStan\Type\UnionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\FluentAnalysis\TypeStringResolver;

#[CoversClass(TypeStringResolver::class)]
final class TypeStringResolverTest extends TestCase
{
    #[Test]
    public function resolvesInt(): void
    {
        self::assertInstanceOf(IntegerType::class, TypeStringResolver::resolve('int'));
    }

    #[Test]
    public function resolvesFloat(): void
    {
        self::assertInstanceOf(FloatType::class, TypeStringResolver::resolve('float'));
    }

    #[Test]
    public function resolvesString(): void
    {
        self::assertInstanceOf(StringType::class, TypeStringResolver::resolve('string'));
    }

    #[Test]
    public function resolvesBool(): void
    {
        self::assertInstanceOf(BooleanType::class, TypeStringResolver::resolve('bool'));
    }

    #[Test]
    public function resolvesTrue(): void
    {
        $type = TypeStringResolver::resolve('true');
        self::assertInstanceOf(ConstantBooleanType::class, $type);
        self::assertTrue($type->getValue());
    }

    #[Test]
    public function resolvesFalse(): void
    {
        $type = TypeStringResolver::resolve('false');
        self::assertInstanceOf(ConstantBooleanType::class, $type);
        self::assertFalse($type->getValue());
    }

    #[Test]
    public function resolvesNull(): void
    {
        self::assertInstanceOf(NullType::class, TypeStringResolver::resolve('null'));
    }

    #[Test]
    public function resolvesArray(): void
    {
        self::assertInstanceOf(ArrayType::class, TypeStringResolver::resolve('array'));
    }

    #[Test]
    public function resolvesObject(): void
    {
        self::assertInstanceOf(ObjectWithoutClassType::class, TypeStringResolver::resolve('object'));
    }

    #[Test]
    public function resolvesCallable(): void
    {
        self::assertInstanceOf(CallableType::class, TypeStringResolver::resolve('callable'));
    }

    #[Test]
    public function resolvesIterable(): void
    {
        self::assertInstanceOf(IterableType::class, TypeStringResolver::resolve('iterable'));
    }

    #[Test]
    public function resolvesResource(): void
    {
        self::assertInstanceOf(ResourceType::class, TypeStringResolver::resolve('resource'));
    }

    #[Test]
    public function resolvesScalarAsUnion(): void
    {
        $type = TypeStringResolver::resolve('scalar');
        self::assertInstanceOf(UnionType::class, $type);
        self::assertTrue($type->isScalar()->yes());
    }

    #[Test]
    public function resolvesNumericString(): void
    {
        $type = TypeStringResolver::resolve('numeric-string');
        self::assertInstanceOf(IntersectionType::class, $type);
    }

    #[Test]
    public function resolvesFqcnAsObjectType(): void
    {
        $type = TypeStringResolver::resolve('DateTimeInterface');
        self::assertInstanceOf(ObjectType::class, $type);
    }

    #[Test]
    public function resolvesUnionType(): void
    {
        $type = TypeStringResolver::resolve('int|string');
        self::assertInstanceOf(UnionType::class, $type);
        self::assertTrue($type->isInteger()->maybe());
        self::assertTrue($type->isString()->maybe());
    }

    #[Test]
    public function resolvesTripleUnion(): void
    {
        $type = TypeStringResolver::resolve('int|float|string');
        self::assertInstanceOf(UnionType::class, $type);
    }

    #[Test]
    public function cacheReturnsSameInstance(): void
    {
        $first = TypeStringResolver::resolve('int');
        $second = TypeStringResolver::resolve('int');
        self::assertSame($first, $second);
    }
}
