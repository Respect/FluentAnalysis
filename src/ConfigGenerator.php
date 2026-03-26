<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis;

use Respect\Fluent\Builders\FluentBuilder;

use function implode;
use function is_subclass_of;

use const PHP_EOL;

final readonly class ConfigGenerator
{
    /** @param list<class-string> $builderClasses */
    public function generate(array $builderClasses): string
    {
        $builderEntries = [];
        $serviceSections = [];

        foreach ($builderClasses as $builderClass) {
            $builderEntries[] = "\t\t\t" . '- builder: ' . $builderClass;

            if (is_subclass_of($builderClass, FluentBuilder::class)) {
                continue;
            }

            $serviceSections[] = $this->formatServiceSection($builderClass);
        }

        $output = 'parameters:' . PHP_EOL
            . "\t" . 'fluent:' . PHP_EOL;

        if ($builderEntries === []) {
            $output .= "\t\t" . 'builders: []' . PHP_EOL;
        } else {
            $output .= "\t\t" . 'builders:' . PHP_EOL
                . implode(PHP_EOL, $builderEntries) . PHP_EOL;
        }

        if ($serviceSections !== []) {
            $output .= PHP_EOL . 'services:' . PHP_EOL
                . implode(PHP_EOL, $serviceSections) . PHP_EOL;
        }

        return $output;
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
}
