<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis;

use PHPStan\Reflection\ClassReflection;

use function in_array;

/**
 * @phpstan-type AssuranceEntry array{
 *     type?: string,
 *     parameterIndex?: int,
 *     from?: string,
 *     compose?: string,
 *     composeRange?: array{int, int|null},
 *     wrapperModifier?: string,
 * }
 */
final readonly class AssuranceMap
{
    /**
     * @param array<string, array<string, AssuranceEntry>> $assurances
     * @param array<string, list<string>> $assertions
     */
    public function __construct(
        private array $assurances = [],
        private array $assertions = [],
    ) {
    }

    /** @return AssuranceEntry|null */
    public function resolveAssurance(ClassReflection $classReflection, string $methodName): array|null
    {
        $className = $classReflection->getName();

        if (isset($this->assurances[$className][$methodName])) {
            return $this->assurances[$className][$methodName];
        }

        foreach ($this->assurances as $registeredClass => $methods) {
            if ($classReflection->is($registeredClass) && isset($methods[$methodName])) {
                return $methods[$methodName];
            }
        }

        return null;
    }

    public function isAssertionMethod(ClassReflection $classReflection, string $methodName): bool
    {
        $className = $classReflection->getName();

        if (isset($this->assertions[$className])) {
            return in_array($methodName, $this->assertions[$className], true);
        }

        foreach ($this->assertions as $registeredClass => $methods) {
            if ($classReflection->is($registeredClass) && in_array($methodName, $methods, true)) {
                return true;
            }
        }

        return false;
    }
}
