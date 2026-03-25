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
use Respect\Fluent\Builders\FluentBuilder;

use function implode;
use function is_array;
use function is_int;
use function is_subclass_of;
use function ksort;
use function sprintf;
use function str_repeat;

use const PHP_EOL;

final readonly class CacheGenerator
{
    public function __construct(
        private MethodMapBuilder $mapBuilder,
    ) {
    }

    /** @param list<class-string> $builderClasses */
    public function generate(array $builderClasses): string
    {
        $methodSections = [];
        $assuranceSections = [];
        $assertionSections = [];
        $serviceSections = [];

        foreach ($builderClasses as $builderClass) {
            $attribute = $this->findAttribute($builderClass);

            if ($attribute === null) {
                continue;
            }

            $map = $this->mapBuilder->build($attribute);
            ksort($map);

            if ($map !== []) {
                $methodSections[] = $this->formatMethodSection($builderClass, $map);
            }

            $assurances = $this->mapBuilder->buildAssurances($attribute);
            ksort($assurances);

            if ($assurances !== []) {
                $assuranceSections[] = $this->formatAssuranceSection($builderClass, $assurances);
            }

            $assertions = $this->mapBuilder->buildAssertions($builderClass);

            if ($assertions !== []) {
                $assertionSections[] = sprintf(
                    '%s%s: [%s]',
                    str_repeat("\t", 3),
                    $builderClass,
                    implode(', ', $assertions),
                );
            }

            if (is_subclass_of($builderClass, FluentBuilder::class)) {
                continue;
            }

            $serviceSections[] = $this->formatServiceSection($builderClass);
        }

        $output = 'parameters:' . PHP_EOL . "\t" . 'fluent:' . PHP_EOL;

        $output .= $this->formatParameterBlock('methods', $methodSections);
        $output .= $this->formatParameterBlock('assurances', $assuranceSections);
        $output .= $this->formatParameterBlock('assertions', $assertionSections);

        if ($serviceSections !== []) {
            $output .= PHP_EOL . 'services:' . PHP_EOL
                . implode(PHP_EOL, $serviceSections) . PHP_EOL;
        }

        return $output;
    }

    /** @param class-string $builderClass */
    private function findAttribute(string $builderClass): FluentNamespace|null
    {
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

    /** @param array<string, string> $map */
    private function formatMethodSection(string $builderClass, array $map): string
    {
        $lines = [];
        foreach ($map as $method => $fqcn) {
            $lines[] = sprintf('%s%s: %s', str_repeat("\t", 4), $method, $fqcn);
        }

        return sprintf(
            '%s%s:' . PHP_EOL . '%s',
            str_repeat("\t", 3),
            $builderClass,
            implode(PHP_EOL, $lines),
        );
    }

    /** @param array<string, array<string, array{int, int|null}|int|string>> $assurances */
    private function formatAssuranceSection(string $builderClass, array $assurances): string
    {
        $lines = [];
        foreach ($assurances as $method => $entry) {
            $parts = [];
            foreach ($entry as $key => $value) {
                $parts[] = $key . ': ' . $this->formatNeonValue($value);
            }

            $lines[] = sprintf('%s%s: { %s }', str_repeat("\t", 4), $method, implode(', ', $parts));
        }

        return sprintf(
            '%s%s:' . PHP_EOL . '%s',
            str_repeat("\t", 3),
            $builderClass,
            implode(PHP_EOL, $lines),
        );
    }

    /** @param list<string> $sections */
    private function formatParameterBlock(string $name, array $sections): string
    {
        if ($sections === []) {
            return "\t\t" . $name . ': []' . PHP_EOL;
        }

        return "\t\t" . $name . ':' . PHP_EOL
            . implode(PHP_EOL, $sections) . PHP_EOL;
    }

    private function formatServiceSection(string $builderClass): string
    {
        $t = "\t";

        return $t . '-' . PHP_EOL
            . $t . $t . 'class: Respect\FluentAnalysis\FluentDynamicReturnTypeExtension' . PHP_EOL
            . $t . $t . 'arguments:' . PHP_EOL
            . $t . $t . $t . 'methodMap: @fluentMethodMap' . PHP_EOL
            . $t . $t . $t . 'assuranceMap: @fluentAssuranceMap' . PHP_EOL
            . $t . $t . $t . 'targetClass: ' . $builderClass . PHP_EOL
            . $t . $t . 'tags:' . PHP_EOL
            . $t . $t . $t . '- phpstan.broker.dynamicMethodReturnTypeExtension' . PHP_EOL
            . $t . $t . $t . '- phpstan.broker.dynamicStaticMethodReturnTypeExtension' . PHP_EOL
            . $t . '-' . PHP_EOL
            . $t . $t . 'class: Respect\FluentAnalysis\FluentTypeSpecifyingExtension' . PHP_EOL
            . $t . $t . 'arguments:' . PHP_EOL
            . $t . $t . $t . 'assuranceMap: @fluentAssuranceMap' . PHP_EOL
            . $t . $t . $t . 'targetClass: ' . $builderClass . PHP_EOL
            . $t . $t . 'tags:' . PHP_EOL
            . $t . $t . $t . '- phpstan.typeSpecifier.methodTypeSpecifyingExtension';
    }

    /** @param array{int, int|null}|int|string $value */
    private function formatNeonValue(array|int|string $value): string
    {
        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                $items[] = $item === null ? 'null' : (string) $item;
            }

            return '[' . implode(', ', $items) . ']';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return match ($value) {
            'true', 'false', 'null', 'yes', 'no', 'on', 'off' => "'" . $value . "'",
            default => $value,
        };
    }
}
