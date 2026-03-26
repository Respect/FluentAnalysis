<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Stubs\ExtraNodes;

final class Custom
{
    public function __construct(
        public readonly string $value = '',
    ) {
    }
}
