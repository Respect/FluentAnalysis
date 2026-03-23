<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis;

use PHPStan\Reflection\ClassReflection;

final readonly class MethodMap
{
    /** @param array<string, array<string, string>> $methods builderClass → [methodName → targetFQCN] */
    public function __construct(
        private array $methods = [],
    ) {
    }

    public function has(ClassReflection $classReflection, string $methodName): bool
    {
        return $this->resolve($classReflection, $methodName) !== null;
    }

    public function resolve(ClassReflection $classReflection, string $methodName): string|null
    {
        $className = $classReflection->getName();

        if (isset($this->methods[$className][$methodName])) {
            return $this->methods[$className][$methodName];
        }

        foreach ($this->methods as $registeredClass => $methods) {
            if ($classReflection->is($registeredClass) && isset($methods[$methodName])) {
                return $methods[$methodName];
            }
        }

        return null;
    }
}
