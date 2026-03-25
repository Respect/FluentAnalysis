<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Node\Printer\ExprPrinter;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\MethodTypeSpecifyingExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use Respect\Fluent\Attributes\AssuranceParameter;

final class FluentTypeSpecifyingExtension implements MethodTypeSpecifyingExtension
{
    use ExtractsAssurance;

    public function __construct(
        private AssuranceMap $assuranceMap,
        private ExprPrinter $exprPrinter,
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

    public function isMethodSupported(
        MethodReflection $methodReflection,
        MethodCall $node,
        TypeSpecifierContext $context,
    ): bool {
        if (
            !$this->assuranceMap->isAssertionMethod(
                $methodReflection->getDeclaringClass(),
                $methodReflection->getName(),
            )
        ) {
            return false;
        }

        $returnType = $methodReflection->getVariants()[0]->getReturnType();

        if ($returnType->isVoid()->yes()) {
            return $context->null();
        }

        if ($returnType->isBoolean()->yes()) {
            return $context->truthy() || $context->falsey();
        }

        return false;
    }

    public function specifyTypes(
        MethodReflection $methodReflection,
        MethodCall $node,
        Scope $scope,
        TypeSpecifierContext $context,
    ): SpecifiedTypes {
        [$sureType, $sureNotType] = $this->extractAssurance($scope->getType($node->var));

        $hasSure = !$sureType instanceof MixedType;
        $hasSureNot = !$sureNotType instanceof NeverType;

        if (!$hasSure && !$hasSureNot) {
            return new SpecifiedTypes();
        }

        $args = $node->getArgs();

        if ($args === []) {
            return new SpecifiedTypes();
        }

        $paramIndex = $this->findAssuranceParameterIndex($methodReflection);
        if (!isset($args[$paramIndex])) {
            return new SpecifiedTypes();
        }

        $inputExpr = $args[$paramIndex]->value;
        $exprString = $this->exprPrinter->printExpr($inputExpr);

        if ($context->falsey()) {
            $sureTypes = $hasSureNot ? [$exprString => [$inputExpr, $sureNotType]] : [];
            $sureNotTypes = $hasSure ? [$exprString => [$inputExpr, $sureType]] : [];

            return new SpecifiedTypes($sureTypes, $sureNotTypes);
        }

        $sureTypes = $hasSure ? [$exprString => [$inputExpr, $sureType]] : [];
        $sureNotTypes = $hasSureNot ? [$exprString => [$inputExpr, $sureNotType]] : [];

        return new SpecifiedTypes($sureTypes, $sureNotTypes);
    }

    private function findAssuranceParameterIndex(MethodReflection $methodReflection): int
    {
        $nativeClass = $methodReflection->getDeclaringClass()->getNativeReflection();
        $nativeMethod = $nativeClass->getMethod($methodReflection->getName());

        foreach ($nativeMethod->getParameters() as $param) {
            if ($param->getAttributes(AssuranceParameter::class) !== []) {
                return $param->getPosition();
            }
        }

        return 0;
    }
}
