<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Stubs;

/**
 * Smoke test: verifies the extension loads and method calls resolve.
 * Detailed type assertions live in tests/fixtures/assertions/*.php
 */
function testBuilder(): TestBuilder
{
    $builder = new TestBuilder();

    return $builder->cors('*')->rateLimit(100);
}
