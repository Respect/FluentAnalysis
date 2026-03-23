<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis;

use Composer\Autoload\ClassLoader;
use ReflectionClass;

use function basename;
use function class_exists;
use function glob;
use function is_array;
use function is_dir;
use function rtrim;
use function sort;
use function spl_autoload_functions;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

readonly class NamespaceClassDiscovery
{
    public function __construct(
        private ClassLoader|null $classLoader = null,
    ) {
    }

    /** @return list<class-string> */
    public function discover(string $namespace): array
    {
        $namespace = rtrim($namespace, '\\') . '\\';
        $directory = $this->findDirectory($namespace);

        if ($directory === null || !is_dir($directory)) {
            return [];
        }

        $classes = [];
        $files = glob($directory . '/*.php');

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            /** @var class-string $fqcn */
            $fqcn = $namespace . basename($file, '.php');

            if (!class_exists($fqcn)) {
                continue;
            }

            $reflection = new ReflectionClass($fqcn);

            if (
                $reflection->isAbstract()
                || $reflection->isInterface()
                || $reflection->isTrait()
                || $reflection->isEnum()
            ) {
                continue;
            }

            $classes[] = $fqcn;
        }

        sort($classes);

        return $classes;
    }

    private function findDirectory(string $namespace): string|null
    {
        $loader = $this->getClassLoader();

        if ($loader === null) {
            return null;
        }

        $prefixes = $loader->getPrefixesPsr4();

        foreach ($prefixes as $prefix => $dirs) {
            if (!str_starts_with($namespace, $prefix)) {
                continue;
            }

            $relative = substr($namespace, strlen($prefix));
            $relative = str_replace('\\', '/', $relative);

            foreach ($dirs as $dir) {
                $candidate = rtrim($dir, '/') . '/' . rtrim($relative, '/');

                if (is_dir($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function getClassLoader(): ClassLoader|null
    {
        if ($this->classLoader !== null) {
            return $this->classLoader;
        }

        foreach (spl_autoload_functions() as $function) {
            if (is_array($function) && $function[0] instanceof ClassLoader) {
                return $function[0];
            }
        }

        return null;
    }
}
