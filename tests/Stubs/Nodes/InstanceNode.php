<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Stubs\Nodes;

use Respect\Fluent\Attributes\Assurance;
use Respect\Fluent\Attributes\AssuranceParameter;

#[Assurance()]
final class InstanceNode
{
    /** @param class-string $class */
    public function __construct(
        #[AssuranceParameter]
        private string $class,
    ) {
    }
}
