<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function array_map;
use function array_slice;
use function count;
use function explode;
use function str_contains;

/** @phpstan-import-type AssuranceEntry from MethodMapBuilder */
final class FluentDynamicReturnTypeExtension implements
    DynamicMethodReturnTypeExtension,
    DynamicStaticMethodReturnTypeExtension
{
    use ExtractsAssurance;

    public function __construct(
        private MethodMap $methodMap,
        private AssuranceMap $assuranceMap,
        ReflectionProvider $reflectionProvider,
        /** @var class-string */
        private string $targetClass = 'Respect\\Fluent\\Builders\\FluentBuilder',
    ) {
        $this->initIsFluentBuilder($targetClass, $reflectionProvider);
    }

    public function getClass(): string
    {
        return $this->targetClass;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        $name = $methodReflection->getName();

        if ($this->isFluentBuilder && $name === 'getNodes') {
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
        $currentAssurance = $this->extractAssurance($callerType);

        if ($this->isFluentBuilder && $methodReflection->getName() === 'getNodes') {
            return $currentTuple;
        }

        $resolved = $this->methodMap->resolve(
            $methodReflection->getDeclaringClass(),
            $methodReflection->getName(),
        );

        if ($resolved !== null) {
            $targetFqcn = str_contains($resolved, ':') ? explode(':', $resolved, 2)[0] : $resolved;

            if ($this->isFluentBuilder) {
                $currentTuple = $this->appendToTuple($currentTuple, $targetFqcn);
            }
        }

        $assurancePair = $this->computeAssurance(
            $currentAssurance,
            $methodReflection,
            $methodCall,
            $scope,
        );

        return $this->buildReturnType($methodReflection, $currentTuple, $assurancePair);
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): Type {
        $currentTuple = new ConstantArrayType([], []);
        /** @var array{Type, Type} $assurancePair */
        $assurancePair = [new MixedType(), new NeverType()];

        $resolved = $this->methodMap->resolve(
            $methodReflection->getDeclaringClass(),
            $methodReflection->getName(),
        );

        if ($resolved !== null) {
            $targetFqcn = str_contains($resolved, ':') ? explode(':', $resolved, 2)[0] : $resolved;

            if ($this->isFluentBuilder) {
                $currentTuple = $this->appendToTuple($currentTuple, $targetFqcn);
            }
        }

        $assurancePair = $this->applyAssuranceEntry(
            $assurancePair,
            $this->assuranceMap->resolveAssurance(
                $methodReflection->getDeclaringClass(),
                $methodReflection->getName(),
            ),
            $methodCall->getArgs(),
            $scope,
        );

        return $this->buildReturnType($methodReflection, $currentTuple, $assurancePair);
    }

    private function extractTuple(Type $type): ConstantArrayType
    {
        if (!$this->isFluentBuilder) {
            return new ConstantArrayType([], []);
        }

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

    /**
     * @param array{Type, Type} $current [sureType, sureNotType]
     *
     * @return array{Type, Type}
     */
    private function computeAssurance(
        array $current,
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): array {
        $entry = $this->assuranceMap->resolveAssurance(
            $methodReflection->getDeclaringClass(),
            $methodReflection->getName(),
        );

        return $this->applyAssuranceEntry($current, $entry, $methodCall->getArgs(), $scope);
    }

    /**
     * @param array{Type, Type} $current [sureType, sureNotType]
     * @param AssuranceEntry|null $entry
     * @param array<Arg> $args
     *
     * @return array{Type, Type}
     */
    private function applyAssuranceEntry(
        array $current,
        array|null $entry,
        array $args,
        Scope $scope,
    ): array {
        if ($entry === null) {
            return $current;
        }

        $narrowedType = $this->resolveEntryType($entry, $args, $scope);

        if ($narrowedType === null) {
            return $current;
        }

        [$sure, $sureNot] = $current;
        $wrapperModifier = $entry['wrapperModifier'] ?? null;

        if ($wrapperModifier === 'exclude') {
            $sureNot = $sureNot instanceof NeverType ? $narrowedType : TypeCombinator::union($sureNot, $narrowedType);

            return [$sure, $sureNot];
        }

        if ($wrapperModifier === 'nullable') {
            $narrowedType = TypeCombinator::addNull($narrowedType);
        }

        $sure = $sure instanceof MixedType ? $narrowedType : TypeCombinator::intersect($sure, $narrowedType);

        return [$sure, $sureNot];
    }

    /**
     * @param AssuranceEntry $entry
     * @param array<Arg> $args
     */
    private function resolveEntryType(array $entry, array $args, Scope $scope): Type|null
    {
        if (isset($entry['type'])) {
            return TypeStringResolver::resolve($entry['type']);
        }

        if (isset($entry['parameterIndex']) && isset($args[$entry['parameterIndex']])) {
            $argIndex = $entry['parameterIndex'];
            $argType = $scope->getType($args[$argIndex]->value);
            $strings = $argType->getConstantStrings();

            if ($strings !== []) {
                $types = array_map(
                    static fn($s) => new ObjectType($s->getValue()),
                    $strings,
                );

                return count($types) === 1 ? $types[0] : TypeCombinator::union(...$types);
            }
        }

        if (isset($entry['from'])) {
            $fromArgIndex = $entry['parameterIndex'] ?? 0;
            if (!isset($args[$fromArgIndex])) {
                return null;
            }

            return match ($entry['from']) {
                'value' => $scope->getType($args[$fromArgIndex]->value),
                'member' => $scope->getType($args[$fromArgIndex]->value)->getIterableValueType(),
                'elements' => $this->resolveElements($args[$fromArgIndex], $scope),
                default => null,
            };
        }

        if (isset($entry['compose']) && $args !== []) {
            $slice = $this->sliceByRange($args, $entry['composeRange'] ?? null);
            $types = [];

            foreach ($slice as $arg) {
                $sure = $this->extractAssurance($scope->getType($arg->value))[0];

                if ($sure instanceof MixedType) {
                    continue;
                }

                $types[] = $sure;
            }

            if ($types === []) {
                return null;
            }

            return match ($entry['compose']) {
                'union' => TypeCombinator::union(...$types),
                'intersect' => TypeCombinator::intersect(...$types),
                default => null,
            };
        }

        return null;
    }

    /**
     * @param array<Arg> $args
     * @param array{int, int|null}|null $range
     *
     * @return array<Arg>
     */
    private function sliceByRange(array $args, array|null $range): array
    {
        if ($range === null) {
            return $args;
        }

        $from = $range[0];
        $to = $range[1];

        if ($to !== null) {
            return array_slice($args, $from, $to - $from + 1);
        }

        return array_slice($args, $from);
    }

    private function resolveElements(Arg $arg, Scope $scope): Type
    {
        $sure = $this->extractAssurance($scope->getType($arg->value))[0];

        if ($sure instanceof MixedType) {
            return new ArrayType(new IntegerType(), new MixedType());
        }

        return new ArrayType(new MixedType(), $sure);
    }

    /** @param array{Type, Type} $assurancePair [sureType, sureNotType] */
    private function buildReturnType(
        MethodReflection $methodReflection,
        ConstantArrayType $tuple,
        array $assurancePair,
    ): Type {
        $className = $methodReflection->getDeclaringClass()->getName();
        [$sure, $sureNot] = $assurancePair;

        if ($this->isFluentBuilder) {
            return new GenericObjectType($className, [$tuple, $sure, $sureNot]);
        }

        return new GenericObjectType($className, [$sure, $sureNot]);
    }
}
