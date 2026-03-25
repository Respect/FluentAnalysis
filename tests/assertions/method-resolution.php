<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Assertions;

use Respect\FluentAnalysis\Test\Stubs\TestBuilder;

use function PHPStan\Testing\assertType;

// Known method returns the builder type with tuple
assertType(
    'Respect\FluentAnalysis\Test\Stubs\TestBuilder<array{Respect\FluentAnalysis\Test\Stubs\Nodes\Cors}, mixed, *NEVER*>',
    (new TestBuilder())->cors('*'),
);

// Chained methods return the builder type with accumulated tuple
assertType(
    'Respect\FluentAnalysis\Test\Stubs\TestBuilder<array{Respect\FluentAnalysis\Test\Stubs\Nodes\Cors, Respect\FluentAnalysis\Test\Stubs\Nodes\RateLimit}, mixed, *NEVER*>',
    (new TestBuilder())->cors('*')->rateLimit(100),
);

// No-arg constructor node resolves
assertType(
    'Respect\FluentAnalysis\Test\Stubs\TestBuilder<array{Respect\FluentAnalysis\Test\Stubs\Nodes\NoConstructor}, mixed, *NEVER*>',
    (new TestBuilder())->noConstructor(),
);

// Static call resolves with tuple
assertType(
    'Respect\FluentAnalysis\Test\Stubs\TestBuilder<array{Respect\FluentAnalysis\Test\Stubs\Nodes\Cors}, mixed, *NEVER*>',
    TestBuilder::cors('*'),
);

// Static call chain returns the builder type with accumulated tuple
assertType(
    'Respect\FluentAnalysis\Test\Stubs\TestBuilder<array{Respect\FluentAnalysis\Test\Stubs\Nodes\Cors, Respect\FluentAnalysis\Test\Stubs\Nodes\RateLimit}, mixed, *NEVER*>',
    TestBuilder::cors('*')->rateLimit(100),
);

// Unknown method is caught by PHPStan
(new TestBuilder())->typo(); // @phpstan-ignore method.notFound
