<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;

use function explode;
use function str_contains;

final class FluentMethodsExtension implements MethodsClassReflectionExtension
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private MethodMap $methodMap,
    ) {
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return $this->methodMap->has($classReflection, $methodName);
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $resolved = $this->methodMap->resolve($classReflection, $methodName) ?? $methodName;

        $prefixClass = null;
        if (str_contains($resolved, ':')) {
            [$targetFqcn, $prefixFqcn] = explode(':', $resolved, 2);
            $prefixClass = $this->reflectionProvider->getClass($prefixFqcn);
        } else {
            $targetFqcn = $resolved;
        }

        $targetClass = $this->reflectionProvider->getClass($targetFqcn);

        return new FluentMethodReflection(
            $classReflection,
            $methodName,
            $targetClass,
            true,
            $prefixClass,
        );
    }
}
