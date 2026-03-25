<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Stubs\Nodes;

use Respect\Fluent\Attributes\Assurance;
use Respect\Fluent\Attributes\AssuranceCompose;

#[Assurance(compose: AssuranceCompose::Intersect)]
final class IntersectNode
{
    public function __construct(
        private object $child1,
        private object $child2,
    ) {
    }
}
