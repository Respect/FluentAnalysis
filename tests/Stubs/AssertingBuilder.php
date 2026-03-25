<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Stubs;

use Respect\Fluent\Attributes\AssuranceAssertion;
use Respect\Fluent\Attributes\AssuranceParameter;
use Respect\Fluent\Attributes\FluentNamespace;
use Respect\Fluent\Builders\Append;
use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Fluent\Resolvers\Ucfirst;

#[FluentNamespace(new NamespaceLookup(new Ucfirst(), null, 'Respect\\FluentAnalysis\\Test\\Stubs\\Nodes'))]
readonly class AssertingBuilder extends Append
{
    public function __construct(object ...$nodes)
    {
        parent::__construct(
            static::factoryFromAttribute(),
            ...$nodes,
        );
    }

    #[AssuranceAssertion]
    public function doAssert(#[AssuranceParameter]
    mixed $input,): void
    {
        // Simulates a void assertion that throws on failure
    }

    #[AssuranceAssertion]
    public function isOk(#[AssuranceParameter]
    mixed $input,): bool
    {
        return true;
    }
}
