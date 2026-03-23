<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Stubs\Nodes;

use Respect\Fluent\Attributes\Composable;

#[Composable('not', without: ['not'])]
final class Not
{
    public function __construct(
        public readonly object $inner,
    ) {
    }
}
