<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis;

use ReflectionClass;
use Respect\Fluent\Attributes\Composable;
use Respect\Fluent\Attributes\FluentNamespace;
use Respect\Fluent\Factories\ComposingLookup;
use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Fluent\FluentNode;
use Respect\Fluent\FluentResolver;

use function assert;
use function in_array;
use function ucfirst;

/** @phpstan-type PrefixInfo array{name: string, prefix: string, optIn: bool, fqcn: class-string, prefixParameter: bool} */
final readonly class MethodMapBuilder
{
    public function __construct(
        private NamespaceClassDiscovery $discovery,
    ) {
    }

    /** @return array<string, string> methodName -> FQCN or FQCN:PrefixFQCN */
    public function build(FluentNamespace $attribute): array
    {
        $factory = $attribute->factory;

        if ($factory instanceof ComposingLookup) {
            $lookup = $factory->lookup;
            $composable = true;
        } else {
            assert($factory instanceof NamespaceLookup);
            $lookup = $factory;
            $composable = false;
        }

        $resolver = $lookup->resolver;
        $namespaces = $lookup->namespaces;

        /** @var array<string, class-string> $map */
        $map = [];
        /** @var array<string, Composable> $composables */
        $composables = [];
        /** @var array<string, PrefixInfo> $prefixes */
        $prefixes = [];

        foreach ($namespaces as $namespace) {
            foreach ($this->discovery->discover($namespace) as $fqcn) {
                $shortName = (new ReflectionClass($fqcn))->getShortName();
                $methodName = $this->classToMethod($shortName, $resolver);
                $map[$methodName] = $fqcn;

                if (!$composable) {
                    continue;
                }

                $composableAttr = $this->getComposable($fqcn);

                if ($composableAttr === null) {
                    continue;
                }

                $composables[$methodName] = $composableAttr;

                if ($composableAttr->prefix === '') {
                    continue;
                }

                $prefixes[$composableAttr->prefix] = [
                    'name' => $shortName,
                    'prefix' => $composableAttr->prefix,
                    'optIn' => $composableAttr->optIn,
                    'fqcn' => $fqcn,
                    'prefixParameter' => $composableAttr->prefixParameter,
                ];
            }
        }

        if ($composable && $prefixes !== []) {
            $this->addComposedMethods($map, $prefixes, $composables, $resolver);
        }

        return $map;
    }

    private function classToMethod(string $className, FluentResolver $resolver): string
    {
        return $resolver->unresolve(new FluentNode($className))->name;
    }

    /** @param class-string $fqcn */
    private function getComposable(string $fqcn): Composable|null
    {
        $reflection = new ReflectionClass($fqcn);
        $attributes = $reflection->getAttributes(Composable::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * @param array<string, string> $map
     * @param array<string, PrefixInfo> $prefixes
     * @param array<string, Composable> $composables
     */
    private function addComposedMethods(
        array &$map,
        array $prefixes,
        array $composables,
        FluentResolver $resolver,
    ): void {
        $baseMethods = $map;

        foreach ($baseMethods as $methodName => $fqcn) {
            $composable = $composables[$methodName] ?? null;

            foreach ($prefixes as $prefix) {
                if ($prefix['optIn']) {
                    if ($composable === null || !in_array($prefix['prefix'], $composable->with, true)) {
                        continue;
                    }
                } elseif ($composable !== null && in_array($prefix['prefix'], $composable->without, true)) {
                    continue;
                }

                $composedName = $prefix['prefix'] . ucfirst($methodName);
                $map[$composedName] = $prefix['prefixParameter'] ? $fqcn . ':' . $prefix['fqcn'] : $fqcn;
            }
        }
    }
}
