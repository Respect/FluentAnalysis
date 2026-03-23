<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Stubs;

/**
 * A builder subclass without its own #[FluentNamespace].
 * The attribute is inherited from TestBuilder's parent chain.
 */
final readonly class ChildBuilder extends TestBuilder
{
}
