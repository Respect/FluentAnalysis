<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Stubs\Nodes;

use Respect\Fluent\Attributes\Assurance;
use Respect\Fluent\Attributes\AssuranceFrom;

#[Assurance(from: AssuranceFrom::Member)]
final class MemberNode
{
    public function __construct(
        private mixed $haystack,
    ) {
    }
}
