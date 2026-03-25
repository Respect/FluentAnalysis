<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis;

use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\CallableType;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\IterableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\ResourceType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function explode;
use function str_contains;

final class TypeStringResolver
{
    /** @var array<string, Type> */
    private static array $cache = [];

    public static function resolve(string $typeString): Type
    {
        if (isset(self::$cache[$typeString])) {
            return self::$cache[$typeString];
        }

        $type = self::doResolve($typeString);
        self::$cache[$typeString] = $type;

        return $type;
    }

    private static function doResolve(string $typeString): Type
    {
        if (str_contains($typeString, '|')) {
            $parts = explode('|', $typeString);
            $types = [];
            foreach ($parts as $part) {
                $types[] = self::resolveSingle($part);
            }

            return TypeCombinator::union(...$types);
        }

        return self::resolveSingle($typeString);
    }

    private static function resolveSingle(string $type): Type
    {
        return match ($type) {
            'int' => new IntegerType(),
            'float' => new FloatType(),
            'string' => new StringType(),
            'bool' => new BooleanType(),
            'true' => new ConstantBooleanType(true),
            'false' => new ConstantBooleanType(false),
            'null' => new NullType(),
            'array' => new ArrayType(new MixedType(), new MixedType()),
            'object' => new ObjectWithoutClassType(),
            'callable' => new CallableType(),
            'iterable' => new IterableType(new MixedType(), new MixedType()),
            'resource' => new ResourceType(),
            'scalar' => TypeCombinator::union(
                new IntegerType(),
                new FloatType(),
                new StringType(),
                new BooleanType(),
            ),
            'numeric-string' => new IntersectionType([new StringType(), new AccessoryNumericStringType()]),
            default => new ObjectType($type),
        };
    }
}
