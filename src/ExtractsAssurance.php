<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use Respect\Fluent\Builders\FluentBuilder;

trait ExtractsAssurance
{
    private bool $isFluentBuilder;

    private function initIsFluentBuilder(string $targetClass, ReflectionProvider $reflectionProvider): void
    {
        $this->isFluentBuilder = $targetClass === FluentBuilder::class
            || ($reflectionProvider->hasClass($targetClass)
                && $reflectionProvider->getClass($targetClass)->isSubclassOf(FluentBuilder::class));
    }

    /** @return array{Type, Type} [sureType, sureNotType] */
    private function extractAssurance(Type $type): array
    {
        // @phpstan-ignore phpstanApi.instanceofType (we control the GenericObjectType creation)
        if (!$type instanceof GenericObjectType) {
            return [new MixedType(), new NeverType()];
        }

        $types = $type->getTypes();
        $sureIndex = $this->isFluentBuilder ? 1 : 0;
        $sureNotIndex = $sureIndex + 1;

        return [
            $types[$sureIndex] ?? new MixedType(),
            $types[$sureNotIndex] ?? new NeverType(),
        ];
    }
}
