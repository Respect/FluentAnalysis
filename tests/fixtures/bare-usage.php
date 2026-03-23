<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Stubs;

/**
 * Without the extension loaded, cors() and rateLimit() are unknown methods.
 * PHPStan should report errors here.
 */
function testWithoutExtension(): TestBuilder
{
    return (new TestBuilder())->cors('*')->rateLimit(100);
}
