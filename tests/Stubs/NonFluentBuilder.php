<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Stubs;

use Respect\Fluent\Attributes\AssuranceAssertion;
use Respect\Fluent\Attributes\FluentNamespace;
use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Fluent\Resolvers\Ucfirst;

/**
 * A builder that does NOT extend FluentBuilder, triggering
 * CacheGenerator's service section output.
 */
#[FluentNamespace(new NamespaceLookup(new Ucfirst(), null, 'Respect\\FluentAnalysis\\Test\\Stubs\\Nodes'))]
final class NonFluentBuilder
{
    #[AssuranceAssertion]
    public function doAssert(mixed $input): void
    {
    }
}
