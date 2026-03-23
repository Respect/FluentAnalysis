<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Stubs\Nodes;

final class RateLimit
{
    public function __construct(
        public readonly int $maxRequests = 60,
    ) {
    }
}
