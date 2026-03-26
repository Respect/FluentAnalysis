<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis;

use ReflectionClass;
use Respect\Fluent\Attributes\FluentNamespace;
use Respect\Fluent\Factories\ComposingLookup;
use Respect\Fluent\Factories\NamespaceLookup;

use function assert;
use function class_exists;
use function in_array;

final readonly class MethodMapFactory
{
    /** @param list<array{builder: string, namespace?: string|null}> $builders */
    public function __construct(
        private array $builders,
        private MethodMapBuilder $mapBuilder = new MethodMapBuilder(new NamespaceClassDiscovery()),
    ) {
    }

    public function createMethodMap(): MethodMap
    {
        $methods = [];

        foreach ($this->groupedBuilders() as $builderClass => $namespaces) {
            $attribute = $this->resolveAttribute($builderClass, $namespaces);

            if ($attribute === null) {
                continue;
            }

            $methods[$builderClass] = $this->mapBuilder->build($attribute);
        }

        return new MethodMap($methods);
    }

    public function createAssuranceMap(): AssuranceMap
    {
        $assurances = [];
        $assertions = [];

        foreach ($this->groupedBuilders() as $builderClass => $namespaces) {
            $attribute = $this->resolveAttribute($builderClass, $namespaces);

            if ($attribute === null) {
                continue;
            }

            $assurances[$builderClass] = $this->mapBuilder->buildAssurances($attribute);
            $assertions[$builderClass] = $this->mapBuilder->buildAssertions($builderClass);
        }

        return new AssuranceMap($assurances, $assertions);
    }

    /** @return array<class-string, list<string>> builderClass => namespaces */
    private function groupedBuilders(): array
    {
        $grouped = [];

        foreach ($this->builders as $entry) {
            /** @var class-string $builder */
            $builder = $entry['builder'];
            $namespace = $entry['namespace'] ?? null;

            if (!isset($grouped[$builder])) {
                $grouped[$builder] = [];
            }

            if ($namespace === null || in_array($namespace, $grouped[$builder], true)) {
                continue;
            }

            $grouped[$builder][] = $namespace;
        }

        return $grouped;
    }

    /** @param list<string> $extraNamespaces */
    private function resolveAttribute(string $builderClass, array $extraNamespaces): FluentNamespace|null
    {
        $attribute = $this->findAttribute($builderClass);

        if ($attribute === null) {
            return null;
        }

        if ($extraNamespaces === []) {
            return $attribute;
        }

        $factory = $attribute->factory;

        if ($factory instanceof ComposingLookup) {
            foreach ($extraNamespaces as $namespace) {
                $factory = $factory->withNamespace($namespace);
            }

            return new FluentNamespace($factory);
        }

        assert($factory instanceof NamespaceLookup);

        foreach ($extraNamespaces as $namespace) {
            $factory = $factory->withNamespace($namespace);
        }

        return new FluentNamespace($factory);
    }

    private function findAttribute(string $builderClass): FluentNamespace|null
    {
        if (!class_exists($builderClass)) {
            return null;
        }

        $reflection = new ReflectionClass($builderClass);

        while (true) {
            $attrs = $reflection->getAttributes(FluentNamespace::class);

            if ($attrs !== []) {
                return $attrs[0]->newInstance();
            }

            $parent = $reflection->getParentClass();

            if ($parent === false) {
                return null;
            }

            $reflection = $parent;
        }
    }
}
