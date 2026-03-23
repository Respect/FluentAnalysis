<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\StaticType;
use PHPStan\Type\Type;

final class FluentMethodReflection implements MethodReflection
{
    public function __construct(
        private ClassReflection $declaringClass,
        private string $methodName,
        private ClassReflection $targetClass,
        private bool $static,
        private ClassReflection|null $prefixClass = null,
    ) {
    }

    public function getDeclaringClass(): ClassReflection
    {
        return $this->declaringClass;
    }

    public function isStatic(): bool
    {
        return $this->static;
    }

    public function isPrivate(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function getDocComment(): string|null
    {
        return $this->targetClass->getNativeReflection()->getDocComment() ?: null;
    }

    public function getName(): string
    {
        return $this->methodName;
    }

    public function getPrototype(): ClassMemberReflection
    {
        return $this;
    }

    /** @return list<ParametersAcceptor> */
    public function getVariants(): array
    {
        $returnType = new StaticType($this->declaringClass);

        if (!$this->targetClass->hasConstructor()) {
            return [
                new FunctionVariant(
                    TemplateTypeMap::createEmpty(),
                    TemplateTypeMap::createEmpty(),
                    [],
                    false,
                    $returnType,
                ),
            ];
        }

        $constructor = $this->targetClass->getConstructor();
        $prefixParameters = $this->getPrefixParameters();
        $variants = [];

        foreach ($constructor->getVariants() as $variant) {
            $variants[] = new FunctionVariant(
                $variant->getTemplateTypeMap(),
                $variant->getResolvedTemplateTypeMap(),
                [...$prefixParameters, ...$variant->getParameters()],
                $variant->isVariadic(),
                $returnType,
            );
        }

        return $variants;
    }

    public function isDeprecated(): TrinaryLogic
    {
        return $this->targetClass->isDeprecated() ? TrinaryLogic::createYes() : TrinaryLogic::createNo();
    }

    public function getDeprecatedDescription(): string|null
    {
        return $this->targetClass->getDeprecatedDescription();
    }

    public function isFinal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function isInternal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getThrowType(): Type|null
    {
        return null;
    }

    public function hasSideEffects(): TrinaryLogic
    {
        return TrinaryLogic::createYes();
    }

    /** @return list<ParameterReflection> */
    private function getPrefixParameters(): array
    {
        if ($this->prefixClass === null || !$this->prefixClass->hasConstructor()) {
            return [];
        }

        $constructor = $this->prefixClass->getConstructor();
        $variants = $constructor->getVariants();

        if ($variants === []) {
            return [];
        }

        $params = $variants[0]->getParameters();

        return $params !== [] ? [$params[0]] : [];
    }
}
