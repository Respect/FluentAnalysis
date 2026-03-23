<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Integration;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\TrinaryLogic;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Respect\Fluent\Builders\Append;
use Respect\FluentAnalysis\FluentMethodReflection;
use Respect\FluentAnalysis\FluentMethodsExtension;
use Respect\FluentAnalysis\MethodMap;
use Respect\FluentAnalysis\Test\Stubs\TestBuilder;

#[CoversClass(FluentMethodsExtension::class)]
#[CoversClass(FluentMethodReflection::class)]
final class FluentMethodsExtensionTest extends PHPStanTestCase
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
    public function hasMethodReturnsTrueForRegisteredMethod(): void
    {
        $extension = $this->createExtension();
        $classReflection = $this->getReflectionProvider()->getClass(TestBuilder::class);

        self::assertTrue($extension->hasMethod($classReflection, 'cors'));
        self::assertTrue($extension->hasMethod($classReflection, 'rateLimit'));
    }

    #[Test]
    public function hasMethodReturnsFalseForUnknownMethod(): void
    {
        $extension = $this->createExtension();
        $classReflection = $this->getReflectionProvider()->getClass(TestBuilder::class);

        self::assertFalse($extension->hasMethod($classReflection, 'typo'));
        self::assertFalse($extension->hasMethod($classReflection, 'unknownMethod'));
    }

    #[Test]
    public function getMethodReturnsFluentMethodReflection(): void
    {
        $extension = $this->createExtension();
        $classReflection = $this->getReflectionProvider()->getClass(TestBuilder::class);

        $method = $extension->getMethod($classReflection, 'cors');

        self::assertInstanceOf(FluentMethodReflection::class, $method);
        self::assertSame('cors', $method->getName());
        self::assertTrue($method->isPublic());
        self::assertFalse($method->isPrivate());
        self::assertTrue($method->isStatic());
        self::assertSame($classReflection, $method->getDeclaringClass());
        self::assertSame($method, $method->getPrototype());
        self::assertSame(TrinaryLogic::createNo(), $method->isFinal());
        self::assertSame(TrinaryLogic::createNo(), $method->isInternal());
        self::assertNull($method->getThrowType());
        self::assertSame(TrinaryLogic::createYes(), $method->hasSideEffects());
    }

    #[Test]
    public function methodReflectionExtractsConstructorParameters(): void
    {
        $extension = $this->createExtension();
        $classReflection = $this->getReflectionProvider()->getClass(TestBuilder::class);

        $method = $extension->getMethod($classReflection, 'cors');
        $variants = $method->getVariants();

        self::assertCount(1, $variants);
        self::assertCount(1, $variants[0]->getParameters());
        self::assertSame('origin', $variants[0]->getParameters()[0]->getName());
    }

    #[Test]
    public function noConstructorNodeHasNoParameters(): void
    {
        $extension = $this->createExtension();
        $classReflection = $this->getReflectionProvider()->getClass(TestBuilder::class);

        $method = $extension->getMethod($classReflection, 'noConstructor');
        $variants = $method->getVariants();

        self::assertCount(1, $variants);
        self::assertCount(0, $variants[0]->getParameters());
    }

    #[Test]
    public function methodResolvesViaParentClassFallback(): void
    {
        // Register methods under Append (parent), query via TestBuilder (child)
        $extension = new FluentMethodsExtension(
            $this->getReflectionProvider(),
            new MethodMap([
                Append::class => ['cors' => 'Respect\\FluentAnalysis\\Test\\Stubs\\Nodes\\Cors'],
            ]),
        );
        $classReflection = $this->getReflectionProvider()->getClass(TestBuilder::class);

        self::assertTrue($extension->hasMethod($classReflection, 'cors'));
        self::assertSame('cors', $extension->getMethod($classReflection, 'cors')->getName());
    }

    #[Test]
    public function hasMethodReturnsFalseForUnregisteredClass(): void
    {
        $extension = $this->createExtension();
        // stdClass is not registered and not a FluentBuilder subclass
        $classReflection = $this->getReflectionProvider()->getClass('stdClass');

        self::assertFalse($extension->hasMethod($classReflection, 'cors'));
    }

    private function createExtension(): FluentMethodsExtension
    {
        $methods = [
            TestBuilder::class => [
                'cors' => 'Respect\\FluentAnalysis\\Test\\Stubs\\Nodes\\Cors',
                'rateLimit' => 'Respect\\FluentAnalysis\\Test\\Stubs\\Nodes\\RateLimit',
                'noConstructor' => 'Respect\\FluentAnalysis\\Test\\Stubs\\Nodes\\NoConstructor',
                'deprecated' => 'Respect\\FluentAnalysis\\Test\\Stubs\\Nodes\\DeprecatedNode',
            ],
        ];

        return new FluentMethodsExtension($this->getReflectionProvider(), new MethodMap($methods));
    }

    private function getReflectionProvider(): ReflectionProvider
    {
        return self::getContainer()->getByType(ReflectionProvider::class);
    }
}
