<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Unit;

use Respect\FluentAnalysis\BuilderClassScanner;

final readonly class FakeScanner extends BuilderClassScanner
{
    /** @param list<class-string> $classes */
    public function __construct(
        private array $classes = [],
    ) {
    }

    /** @return list<class-string> */
    public function scan(string $composerJsonPath): array
    {
        return $this->classes;
    }
}
