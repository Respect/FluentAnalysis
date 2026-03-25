<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Stubs\Nodes;

use Respect\Fluent\Attributes\Composable;
use Respect\Fluent\Attributes\ComposableParameter;

#[Composable(self::class)]
final class Key
{
    public function __construct(
        #[ComposableParameter]
        public readonly string $key,
        public readonly object $inner,
    ) {
    }
}
