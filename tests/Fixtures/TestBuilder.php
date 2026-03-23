<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Fixtures;

use Respect\Fluent\Attributes\FluentNamespace;
use Respect\Fluent\Builders\Append;
use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Fluent\Resolvers\Ucfirst;

#[FluentNamespace(namespaces: [Nodes::class])]
final readonly class TestBuilder extends Append
{
    public function __construct(object ...$nodes)
    {
        parent::__construct(
            new NamespaceLookup(
                new Ucfirst(),
                null,
                Nodes::class,
            ),
            ...$nodes,
        );
    }
}
