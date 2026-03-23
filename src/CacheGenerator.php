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

use function implode;
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
        $sections = [];

        foreach ($builderClasses as $builderClass) {
            $attribute = $this->findAttribute($builderClass);

            if ($attribute === null) {
                continue;
            }

            $map = $this->mapBuilder->build($attribute);
            ksort($map);

            if ($map === []) {
                continue;
            }

            $lines = [];
            foreach ($map as $method => $fqcn) {
                $lines[] = sprintf('%s%s: %s', str_repeat("\t", 4), $method, $fqcn);
            }

            $sections[] = sprintf(
                '%s%s:' . PHP_EOL . '%s',
                str_repeat("\t", 3),
                $builderClass,
                implode(PHP_EOL, $lines),
            );
        }

        if ($sections === []) {
            return 'parameters:' . PHP_EOL . "\t" . 'fluent:' . PHP_EOL . "\t\t" . 'methods: []' . PHP_EOL;
        }

        return 'parameters:' . PHP_EOL
            . "\t" . 'fluent:' . PHP_EOL
            . "\t\t" . 'methods:' . PHP_EOL
            . implode(PHP_EOL, $sections) . PHP_EOL;
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
}
