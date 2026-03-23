<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Integration\TypeInference;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\TrinaryLogic;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Respect\FluentAnalysis\FluentMethodReflection;

#[CoversClass(FluentMethodReflection::class)]
final class DeprecationTest extends PHPStanTestCase
{
    /** @return list<string> */
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/../../../extension.neon',
            __DIR__ . '/../../fixtures/fluent.neon',
        ];
    }

    #[Test]
    public function deprecatedTargetClassMakesMethodDeprecated(): void
    {
        $reflectionProvider = self::getContainer()->getByType(ReflectionProvider::class);
        $builderClass = $reflectionProvider->getClass('Respect\\FluentAnalysis\\Test\\Stubs\\TestBuilder');
        $targetClass = $reflectionProvider->getClass('Respect\\FluentAnalysis\\Test\\Stubs\\Nodes\\DeprecatedNode');

        $reflection = new FluentMethodReflection($builderClass, 'deprecated', $targetClass, false);

        self::assertSame(TrinaryLogic::createYes(), $reflection->isDeprecated());
        self::assertSame('Use Cors instead', $reflection->getDeprecatedDescription());
    }

    #[Test]
    public function nonDeprecatedTargetClassMakesMethodNotDeprecated(): void
    {
        $reflectionProvider = self::getContainer()->getByType(ReflectionProvider::class);
        $builderClass = $reflectionProvider->getClass('Respect\\FluentAnalysis\\Test\\Stubs\\TestBuilder');
        $targetClass = $reflectionProvider->getClass('Respect\\FluentAnalysis\\Test\\Stubs\\Nodes\\Cors');

        $reflection = new FluentMethodReflection($builderClass, 'cors', $targetClass, false);

        self::assertSame(TrinaryLogic::createNo(), $reflection->isDeprecated());
        self::assertNull($reflection->getDeprecatedDescription());
    }

    #[Test]
    public function docCommentIsForwardedFromTargetClass(): void
    {
        $reflectionProvider = self::getContainer()->getByType(ReflectionProvider::class);
        $builderClass = $reflectionProvider->getClass('Respect\\FluentAnalysis\\Test\\Stubs\\TestBuilder');
        $targetClass = $reflectionProvider->getClass('Respect\\FluentAnalysis\\Test\\Stubs\\Nodes\\DeprecatedNode');

        $reflection = new FluentMethodReflection($builderClass, 'deprecated', $targetClass, false);

        self::assertStringContainsString('@deprecated', $reflection->getDocComment() ?? '');
    }

    #[Test]
    public function nullDocCommentWhenTargetHasNone(): void
    {
        $reflectionProvider = self::getContainer()->getByType(ReflectionProvider::class);
        $builderClass = $reflectionProvider->getClass('Respect\\FluentAnalysis\\Test\\Stubs\\TestBuilder');
        $targetClass = $reflectionProvider->getClass('Respect\\FluentAnalysis\\Test\\Stubs\\Nodes\\NoConstructor');

        $reflection = new FluentMethodReflection($builderClass, 'noConstructor', $targetClass, false);

        self::assertNull($reflection->getDocComment());
    }
}
