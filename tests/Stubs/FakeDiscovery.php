<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Stubs;

use Respect\FluentAnalysis\NamespaceClassDiscovery;

final readonly class FakeDiscovery extends NamespaceClassDiscovery
{
    /** @param array<string, list<class-string>> $map namespace => classes */
    public function __construct(
        private array $map = [],
    ) {
    }

    /** @return list<class-string> */
    public function discover(string $namespace): array
    {
        return $this->map[$namespace] ?? [];
    }
}
