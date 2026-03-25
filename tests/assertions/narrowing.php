<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Assertions;

use Respect\FluentAnalysis\Test\Stubs\AssertingBuilder;
use Respect\FluentAnalysis\Test\Stubs\Nodes\Cors;

use function PHPStan\Testing\assertType;

function narrowingAssertions(mixed $x): void
{
    // Void assertion narrows type unconditionally
    (new AssertingBuilder())->intNode()->doAssert($x);
    assertType('int', $x);
}

function narrowingWithUnionType(mixed $x): void
{
    // Union type narrowing
    (new AssertingBuilder())->numericNode()->doAssert($x);
    assertType('float|int|numeric-string', $x);
}

function narrowingWithInstance(mixed $x): void
{
    // Dynamic narrowing via parameter
    (new AssertingBuilder())->instanceNode(Cors::class)->doAssert($x);
    assertType('Respect\FluentAnalysis\Test\Stubs\Nodes\Cors', $x);
}

function narrowingChainIntersection(mixed $x): void
{
    // Chain accumulation: int ∩ (int|float|numeric-string) = int
    (new AssertingBuilder())->intNode()->numericNode()->doAssert($x);
    assertType('int', $x);
}

function noNarrowingCarriesForward(mixed $x): void
{
    // No-narrowing node carries forward
    (new AssertingBuilder())->intNode()->noConstructor()->doAssert($x);
    assertType('int', $x);
}

function boolTypeGuardTruthy(mixed $x): void
{
    // Bool type guard narrows inside if-branch
    if (!(new AssertingBuilder())->intNode()->isOk($x)) {
        return;
    }

    assertType('int', $x);
}

function boolTypeGuardFalsey(int|string $x): void
{
    // Falsey branch removes the narrowed type
    if ((new AssertingBuilder())->intNode()->isOk($x)) {
        return;
    }

    assertType('string', $x);
}

// --- Value mode ---

function valueNarrowingInt(mixed $x): void
{
    (new AssertingBuilder())->valueNode(42)->doAssert($x);
    assertType('42', $x);
}

function valueNarrowingString(mixed $x): void
{
    (new AssertingBuilder())->valueNode('foo')->doAssert($x);
    assertType("'foo'", $x);
}

// --- Member mode ---

function memberNarrowing(mixed $x): void
{
    (new AssertingBuilder())->memberNode(['active', 'inactive'])->doAssert($x);
    assertType("'active'|'inactive'", $x);
}

function memberNarrowingInts(mixed $x): void
{
    (new AssertingBuilder())->memberNode([1, 2, 3])->doAssert($x);
    assertType('1|2|3', $x);
}

// --- Children mode (union) ---

function childrenUnion(mixed $x): void
{
    $a = (new AssertingBuilder())->intNode();
    $b = (new AssertingBuilder())->numericNode();
    (new AssertingBuilder())->unionNode($a, $b)->doAssert($x);
    assertType('float|int|numeric-string', $x);
}

// --- Children mode (intersect) ---

function childrenIntersect(mixed $x): void
{
    $a = (new AssertingBuilder())->intNode();
    $b = (new AssertingBuilder())->numericNode();
    (new AssertingBuilder())->intersectNode($a, $b)->doAssert($x);
    assertType('int', $x);
}

// --- Elements mode ---

function elementsNarrowing(mixed $x): void
{
    $inner = (new AssertingBuilder())->intNode();
    (new AssertingBuilder())->elementsNode($inner)->doAssert($x);
    assertType('array<int>', $x);
}
