<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

use function count;

final class FluentDynamicReturnTypeExtension implements
    DynamicMethodReturnTypeExtension,
    DynamicStaticMethodReturnTypeExtension
{
    public function __construct(
        private MethodMap $methodMap,
    ) {
    }

    public function getClass(): string
    {
        return 'Respect\\Fluent\\Builders\\FluentBuilder';
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        $name = $methodReflection->getName();

        if ($name === 'getNodes') {
            return true;
        }

        return $this->methodMap->has($methodReflection->getDeclaringClass(), $name);
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $this->methodMap->has($methodReflection->getDeclaringClass(), $methodReflection->getName());
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type {
        $callerType = $scope->getType($methodCall->var);
        $currentTuple = $this->extractTuple($callerType);

        if ($methodReflection->getName() === 'getNodes') {
            return $currentTuple;
        }

        $targetFqcn = $this->methodMap->resolve(
            $methodReflection->getDeclaringClass(),
            $methodReflection->getName(),
        );

        if ($targetFqcn !== null) {
            $currentTuple = $this->appendToTuple($currentTuple, $targetFqcn);
        }

        $className = $methodReflection->getDeclaringClass()->getName();

        return new GenericObjectType($className, [$currentTuple]);
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): Type {
        $currentTuple = new ConstantArrayType([], []);

        $targetFqcn = $this->methodMap->resolve(
            $methodReflection->getDeclaringClass(),
            $methodReflection->getName(),
        );

        if ($targetFqcn !== null) {
            $currentTuple = $this->appendToTuple($currentTuple, $targetFqcn);
        }

        $className = $methodReflection->getDeclaringClass()->getName();

        return new GenericObjectType($className, [$currentTuple]);
    }

    private function extractTuple(Type $type): ConstantArrayType
    {
        // @phpstan-ignore phpstanApi.instanceofType (we control the GenericObjectType creation)
        if ($type instanceof GenericObjectType && $type->getTypes() !== []) {
            $inner = $type->getTypes()[0];
            $arrays = $inner->getConstantArrays();

            if ($arrays !== []) {
                return $arrays[0];
            }
        }

        return new ConstantArrayType([], []);
    }

    private function appendToTuple(ConstantArrayType $tuple, string $fqcn): ConstantArrayType
    {
        $nextIndex = count($tuple->getKeyTypes());
        $appended = $tuple->setOffsetValueType(
            new ConstantIntegerType($nextIndex),
            new ObjectType($fqcn),
        );

        $arrays = $appended->getConstantArrays();

        if ($arrays !== []) {
            return $arrays[0];
        }

        return $tuple;
    }
}
