<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis;

use ReflectionClass;
use Respect\Fluent\Attributes\Assurance;
use Respect\Fluent\Attributes\AssuranceAssertion;
use Respect\Fluent\Attributes\AssuranceModifier;
use Respect\Fluent\Attributes\AssuranceParameter;
use Respect\Fluent\Attributes\Composable;
use Respect\Fluent\Attributes\ComposableParameter;
use Respect\Fluent\Attributes\FluentNamespace;
use Respect\Fluent\Factories\ComposingLookup;
use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Fluent\FluentNode;
use Respect\Fluent\FluentResolver;

use function assert;
use function in_array;
use function ucfirst;

/**
 * @phpstan-type PrefixInfo array{
 *     name: string,
 *     prefix: string,
 *     optIn: bool,
 *     fqcn: class-string,
 *     prefixParameter: bool,
 * }
 * @phpstan-type AssuranceEntry array{
 *     type?: string,
 *     parameterIndex?: int,
 *     from?: string,
 *     compose?: string,
 *     composeRange?: array{int, int|null},
 *     wrapperModifier?: string,
 * }
 */
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

                if ($composableAttr->prefix === null) {
                    continue;
                }

                $prefix = $this->classToMethod($shortName, $resolver);
                $prefixes[$prefix] = [
                    'name' => $shortName,
                    'prefix' => $prefix,
                    'optIn' => $composableAttr->optIn,
                    'fqcn' => $fqcn,
                    'prefixParameter' => $this->hasComposableParameter($fqcn),
                ];
            }
        }

        if ($composable && $prefixes !== []) {
            $this->addComposedMethods($map, $prefixes, $composables, $resolver);
        }

        return $map;
    }

    /** @return array<string, AssuranceEntry> methodName -> assurance entry */
    public function buildAssurances(FluentNamespace $attribute): array
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

        /** @var array<string, AssuranceEntry> $assurances */
        $assurances = [];
        /** @var array<string, Composable> $composables */
        $composables = [];
        /** @var array<string, PrefixInfo> $prefixes */
        $prefixes = [];

        foreach ($namespaces as $namespace) {
            foreach ($this->discovery->discover($namespace) as $fqcn) {
                $shortName = (new ReflectionClass($fqcn))->getShortName();
                $methodName = $this->classToMethod($shortName, $resolver);

                $entry = $this->getAssuranceEntry($fqcn);
                if ($entry !== null) {
                    $assurances[$methodName] = $entry;
                }

                if (!$composable) {
                    continue;
                }

                $composableAttr = $this->getComposable($fqcn);

                if ($composableAttr === null) {
                    continue;
                }

                $composables[$methodName] = $composableAttr;

                if ($composableAttr->prefix === null) {
                    continue;
                }

                $prefix = $this->classToMethod($shortName, $resolver);
                $prefixes[$prefix] = [
                    'name' => $shortName,
                    'prefix' => $prefix,
                    'optIn' => $composableAttr->optIn,
                    'fqcn' => $fqcn,
                    'prefixParameter' => $this->hasComposableParameter($fqcn),
                ];
            }
        }

        if ($composable && $prefixes !== []) {
            $this->addComposedAssurances($assurances, $prefixes, $composables);
        }

        return $assurances;
    }

    /**
     * @param class-string $builderClass
     *
     * @return list<string>
     */
    public function buildAssertions(string $builderClass): array
    {
        $reflection = new ReflectionClass($builderClass);
        $methods = [];

        foreach ($reflection->getMethods() as $method) {
            if ($method->getAttributes(AssuranceAssertion::class) === []) {
                continue;
            }

            $methods[] = $method->getName();
        }

        return $methods;
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
                    if ($composable === null || !in_array($prefix['fqcn'], $composable->with, true)) {
                        continue;
                    }
                } elseif ($composable !== null && in_array($prefix['fqcn'], $composable->without, true)) {
                    continue;
                }

                $composedName = $prefix['prefix'] . ucfirst($methodName);
                $map[$composedName] = $prefix['prefixParameter'] ? $fqcn . ':' . $prefix['fqcn'] : $fqcn;
            }
        }
    }

    /**
     * @param class-string $fqcn
     *
     * @return AssuranceEntry|null
     */
    private function getAssuranceEntry(string $fqcn): array|null
    {
        $reflection = new ReflectionClass($fqcn);
        $attributes = $reflection->getAttributes(Assurance::class);

        foreach ($attributes as $attr) {
            $assurance = $attr->newInstance();

            $parameterIndex = $this->findAssuranceParameterIndex($reflection);

            $hasContent = $assurance->type !== null
                || $parameterIndex !== null
                || $assurance->from !== null
                || $assurance->compose !== null;

            if ($assurance->modifier !== null && !$hasContent) {
                continue;
            }

            $entry = [];

            if ($assurance->modifier !== null) {
                $entry['wrapperModifier'] = $assurance->modifier->value;
            }

            if ($assurance->type !== null) {
                $entry['type'] = $assurance->type;
            }

            if ($parameterIndex !== null) {
                $entry['parameterIndex'] = $parameterIndex;
            }

            if ($assurance->from !== null) {
                $entry['from'] = $assurance->from->value;
            }

            if ($assurance->compose !== null) {
                $entry['compose'] = $assurance->compose->value;
            }

            if ($assurance->composeRange !== null) {
                $entry['composeRange'] = $assurance->composeRange;
            }

            return $entry;
        }

        return null;
    }

    /** @param class-string $fqcn */
    private function getAssuranceModifier(string $fqcn): AssuranceModifier|null
    {
        $reflection = new ReflectionClass($fqcn);
        $attributes = $reflection->getAttributes(Assurance::class);

        foreach ($attributes as $attr) {
            $assurance = $attr->newInstance();

            if ($assurance->modifier !== null) {
                return $assurance->modifier;
            }
        }

        return null;
    }

    /**
     * @param array<string, AssuranceEntry> $assurances
     * @param array<string, PrefixInfo> $prefixes
     * @param array<string, Composable> $composables
     */
    private function addComposedAssurances(
        array &$assurances,
        array $prefixes,
        array $composables,
    ): void {
        $baseAssurances = $assurances;

        foreach ($baseAssurances as $methodName => $entry) {
            $composable = $composables[$methodName] ?? null;

            foreach ($prefixes as $prefix) {
                if ($prefix['optIn']) {
                    if ($composable === null || !in_array($prefix['fqcn'], $composable->with, true)) {
                        continue;
                    }
                } elseif ($composable !== null && in_array($prefix['fqcn'], $composable->without, true)) {
                    continue;
                }

                $composedName = $prefix['prefix'] . ucfirst($methodName);
                $wrapperModifier = $this->getAssuranceModifier($prefix['fqcn']);

                if ($wrapperModifier === null) {
                    $assurances[$composedName] = $entry;
                    continue;
                }

                $composed = $entry;
                $composed['wrapperModifier'] = $wrapperModifier->value;
                $assurances[$composedName] = $composed;
            }
        }
    }

    /** @param ReflectionClass<object> $reflection */
    private function findAssuranceParameterIndex(ReflectionClass $reflection): int|null
    {
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return null;
        }

        foreach ($constructor->getParameters() as $param) {
            if ($param->getAttributes(AssuranceParameter::class) !== []) {
                return $param->getPosition();
            }
        }

        return null;
    }

    /** @param class-string $fqcn */
    private function hasComposableParameter(string $fqcn): bool
    {
        $reflection = new ReflectionClass($fqcn);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return false;
        }

        foreach ($constructor->getParameters() as $param) {
            if ($param->getAttributes(ComposableParameter::class) !== []) {
                return true;
            }
        }

        return false;
    }
}
