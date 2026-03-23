<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis;

use DirectoryIterator;
use ReflectionClass;
use Respect\Fluent\Attributes\FluentNamespace;

use function class_exists;
use function file_get_contents;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;

readonly class BuilderClassScanner
{
    /** @return list<class-string> */
    public function scan(string $composerJsonPath): array
    {
        if (!is_file($composerJsonPath)) {
            return [];
        }

        $config = json_decode(file_get_contents($composerJsonPath) ?: '', true);

        if (!is_array($config)) {
            return [];
        }

        $classes = [];

        foreach (['autoload', 'autoload-dev'] as $key) {
            $section = $config[$key] ?? null;

            if (!is_array($section) || !isset($section['psr-4']) || !is_array($section['psr-4'])) {
                continue;
            }

            foreach ($section['psr-4'] as $namespace => $dirs) {
                if (!is_string($namespace)) {
                    continue;
                }

                $dirList = is_array($dirs) ? $dirs : [$dirs];

                foreach ($dirList as $dir) {
                    if (!is_string($dir) || !is_dir($dir)) {
                        continue;
                    }

                    $this->scanDirectory($dir, $namespace, $classes);
                }
            }
        }

        return $classes;
    }

    /** @param list<class-string> $classes */
    private function scanDirectory(string $directory, string $namespace, array &$classes): void
    {
        foreach (new DirectoryIterator($directory) as $file) {
            if ($file->isDot()) {
                continue;
            }

            if ($file->isDir()) {
                $this->scanDirectory(
                    $file->getPathname(),
                    $namespace . $file->getBasename() . '\\',
                    $classes,
                );

                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            /** @var class-string $fqcn */
            $fqcn = $namespace . $file->getBasename('.php');

            if (!class_exists($fqcn)) {
                continue;
            }

            $reflection = new ReflectionClass($fqcn);

            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }

            $attrs = $reflection->getAttributes(FluentNamespace::class);

            if ($attrs === []) {
                continue;
            }

            $classes[] = $fqcn;
        }
    }
}
