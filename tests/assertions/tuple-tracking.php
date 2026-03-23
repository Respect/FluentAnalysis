<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Assertions;

use Respect\FluentAnalysis\Test\Stubs\TestBuilder;

use function PHPStan\Testing\assertType;

// Empty builder returns empty tuple
assertType('array{}', (new TestBuilder())->getNodes());

// Single node returns single-element tuple
assertType(
    'array{Respect\FluentAnalysis\Test\Stubs\Nodes\Cors}',
    (new TestBuilder())->cors('*')->getNodes(),
);

// Two nodes return two-element tuple
assertType(
    'array{Respect\FluentAnalysis\Test\Stubs\Nodes\Cors, Respect\FluentAnalysis\Test\Stubs\Nodes\RateLimit}',
    (new TestBuilder())->cors('*')->rateLimit(100)->getNodes(),
);

// Element access returns the exact node type
$nodes = (new TestBuilder())->cors('*')->rateLimit(100)->getNodes();
assertType('Respect\FluentAnalysis\Test\Stubs\Nodes\Cors', $nodes[0]);
assertType('Respect\FluentAnalysis\Test\Stubs\Nodes\RateLimit', $nodes[1]);

// Tuple accumulates through variable assignments
$a = (new TestBuilder())->cors('*');
$b = $a->rateLimit(100);
assertType(
    'array{Respect\FluentAnalysis\Test\Stubs\Nodes\Cors, Respect\FluentAnalysis\Test\Stubs\Nodes\RateLimit}',
    $b->getNodes(),
);

// Static call bootstraps with single-element tuple
assertType(
    'array{Respect\FluentAnalysis\Test\Stubs\Nodes\Cors}',
    TestBuilder::cors('*')->getNodes(),
);

// Static call then chain accumulates tuple
assertType(
    'array{Respect\FluentAnalysis\Test\Stubs\Nodes\Cors, Respect\FluentAnalysis\Test\Stubs\Nodes\RateLimit}',
    TestBuilder::cors('*')->rateLimit(100)->getNodes(),
);

// Three nodes
assertType(
    'array{Respect\FluentAnalysis\Test\Stubs\Nodes\Cors, Respect\FluentAnalysis\Test\Stubs\Nodes\RateLimit, Respect\FluentAnalysis\Test\Stubs\Nodes\NoConstructor}',
    (new TestBuilder())->cors('*')->rateLimit(100)->noConstructor()->getNodes(),
);
