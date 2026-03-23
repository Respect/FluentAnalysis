# Respect\FluentAnalysis

PHPStan extension for [Respect/Fluent](https://github.com/Respect/Fluent) builders.
Provides method resolution, parameter validation, and tuple-typed `getNodes()`
without generated code.

Fluent builders use `__call` to resolve method names to class instances. Since
those methods don't exist as real declarations, PHPStan reports errors and can't
validate arguments. This extension teaches PHPStan what each method does: its
parameters, return type, and the exact tuple of accumulated nodes.

```php
$stack = Middleware::cors('*')->rateLimit(100);

// PHPStan knows:
//   cors()      accepts (string $origin = '*')
//   rateLimit() accepts (int $maxRequests = 60)
//   getNodes()  returns array{Cors, RateLimit}
//   $stack      is Middleware<array{Cors, RateLimit}>

$stack->getNodes()[0]; // PHPStan infers: Cors
$stack->typo();        // PHPStan error: method not found
$stack->cors(42);      // PHPStan error: int given, string expected
```

## Installation

```bash
composer require --dev respect/fluent-analysis
```

Requires PHP 8.5+ and PHPStan 2.1+.

## Setup

### 1. Generate the method cache

```bash
vendor/bin/fluent-analysis generate
```

This scans your project for builder classes with `#[FluentNamespace]`, reads the
factory configuration from the attribute, and writes a `fluent.neon` file mapping
method names to target classes.

### 2. Include in your PHPStan config

```neon
includes:
    - vendor/respect/fluent-analysis/extension.neon
    - fluent.neon
```

The extension loads automatically via Composer's PHPStan plugin mechanism.
The `fluent.neon` file provides the method map for your specific builders.

### 3. Re-generate when classes change

Run `vendor/bin/fluent-analysis generate` again after adding, removing, or
renaming classes in your fluent namespaces. The command detects unchanged output
and skips the write if nothing changed.

```bash
# Custom output path
vendor/bin/fluent-analysis generate -o phpstan/fluent.neon
```

## Features

### Method resolution

Every method on your builder is resolved to its target class. PHPStan reports
unknown methods as errors: typos are caught at analysis time.

### Constructor parameter forwarding

Method parameters come from the target class constructor. If `Cors` has
`__construct(string $origin = '*')`, then `->cors()` accepts the same
signature. Type mismatches are reported.

### Tuple-typed `getNodes()`

The extension tracks which node types are accumulated through the chain.
`getNodes()` returns a precise tuple instead of `array<int, object>`:

```php
$builder = new MiddlewareStack();
$chain = $builder->cors('*')->rateLimit(100)->auth('bearer');

// PHPStan knows: array{Cors, RateLimit, Auth}
$nodes = $chain->getNodes();

// Individual elements are typed
$nodes[0]; // Cors
$nodes[1]; // RateLimit
$nodes[2]; // Auth
```

Tuple tracking works through variable assignments and static calls:

```php
$a = MiddlewareStack::cors('*');
$b = $a->rateLimit(100);
$b->getNodes(); // array{Cors, RateLimit}
```

### Deprecation forwarding

If a target class is marked `@deprecated`, the fluent method inherits the
deprecation. PHPStan reports it wherever the method is called.

### Composable prefix support

For builders using Respect/Fluent's composable prefixes (like Validation's
`notEmail()`, `nullOrStringType()`), the extension resolves composed methods
with correct parameter signatures.

## How it works

The extension registers two PHPStan hooks:

1. **`FluentMethodsExtension`** (`MethodsClassReflectionExtension`) — tells
   PHPStan which methods exist on each builder, with parameters extracted from
   the target class constructor.

2. **`FluentDynamicReturnTypeExtension`** (`DynamicMethodReturnTypeExtension` +
   `DynamicStaticMethodReturnTypeExtension`) — intercepts each method call to
   track accumulated node types as a `GenericObjectType` wrapping a
   `ConstantArrayType` tuple. When `getNodes()` is called, the tuple is
   returned directly.

Both extensions share a `MethodMap` that resolves method names to target
class FQCNs, with parent-class fallback for builder inheritance.

The `generate` command reads the `#[FluentNamespace]` attribute from each
builder, extracts the factory's resolver and namespaces, discovers classes,
and uses `FluentResolver::unresolve()` to derive method names from class names.

## vs. `@mixin`-style interfaces

|                     | FluentAnalysis                      | `@mixin`                             |
|---------------------|-------------------------------------|--------------------------------------|
| Generated files     | None (one small neon cache)         | Interface files per builder + prefix |
| Return type         | `Builder<array{A, B, C}>`           | `Builder` (via `@mixin`)             |
| `getNodes()` type   | `array{A, B, C}` (exact tuple)      | `array<int, Node>` (generic)         |
| Element access      | `$nodes[0]` typed as `A`            | `mixed`                              |
| Deprecation         | Forwarded automatically             | Must regenerate                      |
| Composable prefixes | Resolved from cache                 | Full method signatures               |
| IDE support         | PHPStan-powered (PhpStorm, VS Code) | Direct IDE autocomplete              |
| Maintenance         | Re-run `generate` on class changes  | Manual/generated                     |

Both approaches work. Use FluentAnalysis for precise type tracking. Use `@mixin`s
for broader IDE autocomplete without PHPStan.
