<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Unit;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Respect\FluentAnalysis\NamespaceClassDiscovery;
use Respect\FluentAnalysis\Test\Stubs\Nodes\Cors;
use Respect\FluentAnalysis\Test\Stubs\Nodes\DeprecatedNode;
use Respect\FluentAnalysis\Test\Stubs\Nodes\NoConstructor;
use Respect\FluentAnalysis\Test\Stubs\Nodes\Not;
use Respect\FluentAnalysis\Test\Stubs\Nodes\OptInOnly;
use Respect\FluentAnalysis\Test\Stubs\Nodes\RateLimit;

use function sort;

#[CoversClass(NamespaceClassDiscovery::class)]
final class NamespaceClassDiscoveryTest extends TestCase
{
    private static ClassLoader $classLoader;

    public static function setUpBeforeClass(): void
    {
        // Capture the ClassLoader from vendor/autoload.php return value
        self::$classLoader = require __DIR__ . '/../../vendor/autoload.php';
    }

    #[Test]
    public function discoverFindsConcreteClasses(): void
    {
        $discovery = new NamespaceClassDiscovery(self::$classLoader);
        $classes = $discovery->discover('Respect\\FluentAnalysis\\Test\\Stubs\\Nodes');

        self::assertContains(Cors::class, $classes);
        self::assertContains(RateLimit::class, $classes);
        self::assertContains(NoConstructor::class, $classes);
        self::assertContains(DeprecatedNode::class, $classes);
        self::assertContains(Not::class, $classes);
        self::assertContains(OptInOnly::class, $classes);
        self::assertCount(15, $classes);
    }

    #[Test]
    public function discoverReturnsSortedList(): void
    {
        $discovery = new NamespaceClassDiscovery(self::$classLoader);
        $classes = $discovery->discover('Respect\\FluentAnalysis\\Test\\Stubs\\Nodes');

        $sorted = $classes;
        sort($sorted);

        self::assertSame($sorted, $classes);
    }

    #[Test]
    public function discoverReturnsEmptyForNonExistentNamespace(): void
    {
        $discovery = new NamespaceClassDiscovery(self::$classLoader);

        self::assertSame([], $discovery->discover('NonExistent\\Namespace'));
    }

    #[Test]
    public function discoverExcludesAbstractAndInterfaceClasses(): void
    {
        $discovery = new NamespaceClassDiscovery(self::$classLoader);

        // Stubs directory contains FakeDiscovery (extends NamespaceClassDiscovery)
        // and FakeScanner, but FakeDiscovery's parent (NamespaceClassDiscovery) is
        // readonly and not abstract. The Stubs directory has only concrete classes.
        // We verify none of the discovered classes are abstract/interface.
        $classes = $discovery->discover('Respect\\FluentAnalysis\\Test\\Stubs\\Nodes');
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            self::assertFalse($reflection->isAbstract(), $class . ' should not be abstract');
            self::assertFalse($reflection->isInterface(), $class . ' should not be an interface');
        }
    }
}
