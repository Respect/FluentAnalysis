<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\FluentAnalysis\Test\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\FluentAnalysis\FluentMethodReflection;
use Respect\FluentAnalysis\FluentMethodsExtension;

use function exec;
use function implode;
use function json_decode;
use function preg_match;
use function preg_replace;

#[CoversClass(FluentMethodsExtension::class)]
#[CoversClass(FluentMethodReflection::class)]
final class FluentAnalysisIntegrationTest extends TestCase
{
    #[Test]
    public function phpstanFixtureAnalysisPassesWithExtension(): void
    {
        self::assertSame(
            [
                'totals' => ['errors' => 0, 'file_errors' => 0],
                'files' => [],
                'errors' => [],
            ],
            self::runPhpstan('tests/fixtures/phpstan-test.neon'),
        );
    }

    #[Test]
    public function phpstanReportsErrorsWithoutExtension(): void
    {
        self::assertSame(
            [
                'totals' => ['errors' => 0, 'file_errors' => 3],
                'files' => [
                    'tests/fixtures/bare-usage.php' => [
                        'errors' => 3,
                        'messages' => [
                            [
                                'message' => 'Call to an undefined method Respect'
                                    . '\FluentAnalysis\Test\Stubs\TestBuilder::cors().',
                                'line' => 18,
                                'ignorable' => true,
                                'identifier' => 'method.notFound',
                            ],
                            [
                                'message' => 'Cannot call method rateLimit() on mixed.',
                                'line' => 18,
                                'ignorable' => true,
                                'identifier' => 'method.nonObject',
                            ],
                            [
                                'message' => 'Function Respect\FluentAnalysis\Test\Stubs'
                                    . '\testWithoutExtension() should return Respect'
                                    . '\FluentAnalysis\Test\Stubs\TestBuilder but returns mixed.',
                                'line' => 18,
                                'ignorable' => true,
                                'identifier' => 'return.type',
                            ],
                        ],
                    ],
                ],
                'errors' => [],
            ],
            self::runPhpstan('tests/fixtures/no-extension.neon'),
        );
    }

    /** @return array<string, mixed> */
    private static function runPhpstan(string $config): array
    {
        $output = [];
        exec(
            'vendor/bin/phpstan analyse -c ' . $config . ' --error-format=json --no-progress 2>&1',
            $output,
        );

        $json = implode("\n", $output);
        preg_match('/(\{.*\})/s', $json, $matches);
        $result = json_decode($matches[1] ?? '{}', true);

        // Normalize absolute paths to relative
        $normalized = [];
        foreach ($result['files'] ?? [] as $path => $data) {
            $relative = preg_replace('#^.*/tests/#', 'tests/', $path);
            $normalized[$relative] = $data;
        }

        $result['files'] = $normalized;

        return $result;
    }
}
