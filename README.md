# Respect\FluentAnalysis

PHPStan extension for [Respect/Fluent](https://github.com/Respect/Fluent) builders.
Provides method resolution, parameter validation, tuple-typed `getNodes()`, and
type narrowing through assertion methods.

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

Libraries that ship a Fluent builder declare it in their `fluent.neon`:

```neon
parameters:
    fluent:
        builders:
            - builder: App\MiddlewareStack
```

The extension loads automatically via
[phpstan/extension-installer](https://github.com/phpstan/extension-installer).
Method maps are built from `#[FluentNamespace]` attributes at PHPStan boot.

### Adding custom namespaces

To add extra node namespaces to an existing builder (e.g. custom validators):

```neon
parameters:
    fluent:
        builders:
            - builder: Respect\Validation\ValidatorBuilder
              namespace: App\Validators
```

Entries from multiple neon files are merged automatically. Each package,
extension, or user project can append entries independently.

### Generating config for new projects

For projects that define their own `#[FluentNamespace]` builders:

```bash
vendor/bin/fluent-analysis generate
```

This scans your `composer.json` autoload entries for builder classes and writes
a `fluent.neon` with the builder list and service registrations.

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

### Type narrowing

Builders can narrow the type of a variable through assertion methods. Node
classes declare their assurance via the `#[Assurance]` attribute, assertion
methods are marked with `#[AssuranceAssertion]`, and `#[AssuranceParameter]`
identifies the validated parameter and constructor parameters used for type
resolution.

Void assertion methods narrow unconditionally:

```php
$builder->intNode()->doAssert($x);
// PHPStan now knows $x is int
```

Bool assertion methods work as type guards:

```php
if ($builder->intNode()->isOk($x)) {
    // $x is int here
}
// $x is not int here
```

Chained nodes intersect their assurances:

```php
$builder->intNode()->numericNode()->doAssert($x);
// int ∩ (int|float|numeric-string) = int
```

The extension supports several assurance modes through the `#[Assurance]`
attribute:

- **`type`** — a fixed type string (e.g. `int`, `float|int|numeric-string`)
- **`#[AssuranceParameter]`** — the type is taken from a constructor parameter
  annotated with the attribute (e.g. a class-string parameter)
- **`from: value`** — narrows to the argument's literal type
- **`from: member`** — narrows to the iterable value type of the argument
- **`from: elements`** — narrows to an array of the inner assurance type
- **`compose: union|intersect`** — combines assurances from multiple builder
  arguments

## How it works

The extension registers three PHPStan hooks:

1. **`FluentMethodsExtension`** (`MethodsClassReflectionExtension`) — tells
   PHPStan which methods exist on each builder, with parameters extracted from
   the target class constructor.

2. **`FluentDynamicReturnTypeExtension`** (`DynamicMethodReturnTypeExtension` +
   `DynamicStaticMethodReturnTypeExtension`) — intercepts each method call to
   track accumulated node types as a `GenericObjectType` wrapping a
   `ConstantArrayType` tuple. When `getNodes()` is called, the tuple is
   returned directly. Also accumulates assurance types through the chain.

3. **`FluentTypeSpecifyingExtension`** (`MethodTypeSpecifyingExtension`) —
   enables type narrowing in control flow. When a builder's assertion method
   is called, accumulated assurances are applied to narrow the input variable's
   type. Supports void assertions (unconditional) and bool guards (conditional).

The extensions share a `MethodMap` for method resolution and an `AssuranceMap`
for type narrowing configuration, both with parent-class fallback for builder
inheritance.

At PHPStan boot, `MethodMapFactory` reads the `builders` parameter, reflects
each builder's `#[FluentNamespace]` attribute, discovers classes in the
declared namespaces, and builds the method/assurance maps. Extra namespaces
from user config are merged via `withNamespace()`.

## FluentAnalysis vs FluentGen

Another similar project is [FluentGen](https://github.com/Respect/FluentGen).

Both are complementary, offering IDE support and type inference as separate packages.

|                     | FluentAnalysis                       | FluentGen                            |
|---------------------|--------------------------------------|--------------------------------------|
| Generated files     | None                                 | Interface files per builder + prefix |
| Return type         | `Builder<array{A, B, C}>`            | `Builder` (via `@mixin`)             |
| `getNodes()` type   | `array{A, B, C}` (exact tuple)       | `array<int, Node>` (generic)         |
| Element access      | `$nodes[0]` typed as `A`             | `mixed`                              |
| Deprecation         | Forwarded automatically              | Must regenerate                      |
| Composable prefixes | Resolved from attribute              | Full method signatures               |
| Type narrowing      | Assertion methods narrow input types | Not supported                        |
| IDE support         | PHPStan-powered (PhpStorm, VS Code)  | Direct IDE autocomplete              |
