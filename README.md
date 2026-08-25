# rasuvaeff/understudy-phpstan

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/understudy-phpstan/v)](https://packagist.org/packages/rasuvaeff/understudy-phpstan)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/understudy-phpstan/downloads)](https://packagist.org/packages/rasuvaeff/understudy-phpstan)
[![Build](https://github.com/rasuvaeff/understudy-phpstan/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/understudy-phpstan/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/understudy-phpstan/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/understudy-phpstan/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/understudy-phpstan/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/understudy-phpstan/php)](https://packagist.org/packages/rasuvaeff/understudy-phpstan)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[Русская версия](README.ru.md)

PHPStan extension for [understudy](https://github.com/rasuvaeff/understudy).

> Using an AI coding assistant? Point it at [llms.txt](llms.txt).

## Requirements

- PHP 8.3 - 8.5
- `phpstan/phpstan` ^2.1
- `rasuvaeff/understudy` ^0.1

## Installation

```bash
composer require --dev rasuvaeff/understudy-phpstan
```

With [phpstan/extension-installer](https://github.com/phpstan/extension-installer)
that is all. Without it, include the extension yourself:

```neon
includes:
    - vendor/rasuvaeff/understudy-phpstan/extension.neon
```

## What it does

understudy specifies a call by making it inside a closure:

```php
when(fn () => $repository->find(Arg::int(min: 1)))->returns($book);
```

Four things about that line are invisible to PHPStan on its own, and this
extension is those four things.

### 1. A matcher fits whatever the contract declares

`Arg::int()` is declared `mixed`, because a matcher has to be passable
wherever a contract declares anything at all. At level 9 and above PHPStan
reports it as `Parameter #1 $id … expects int, mixed given` — correct about
the type, wrong about the code.

The extension types every matcher as `never`, the bottom type, which every
parameter accepts. **Nothing is suppressed.** A wrong argument beside a
matcher, a method the double does not have, the statements around the
closure — all keep their reports:

```php
when(fn () => $repository->rename(Arg::any(), 'not an int'));
//                                            ^^^^^^^^^^^^ still reported
when(fn () => $repository->missing(Arg::any()));
//                        ^^^^^^^ still reported
```

Below level 9 there is nothing to fix here — PHPStan does not check `mixed`
against a declared parameter — and the rest of the extension works at every
level.

### 2. `returns()` is checked against the method being specified

The core declares `when(): WhenBuilder<mixed>`, and it has no choice: which
method is being specified is known only from the closure. The extension fills
the template parameter in, and PHPStan does the rest:

```php
when(fn () => $gate->open(1))->returns('yes');
// Parameter #1 ...$values of method WhenBuilder<bool>::returns() expects bool, string given.
```

### 3. `wire()` has the shape of the class it wired

```php
$wired = Understudy::wire(Checkout::class);

$wired['doubles']['repository'];  // Offset 'repository' does not exist on
                                  // array{books: BookRepository, clock: Clock}.
$wired['doubles']['clock']->tick();  // Call to an undefined method Clock::tick().
```

### 4. Specifications that cannot work are reported

Each of these has a runtime counterpart — the engine throws, or the
expectation never matches. Reporting them statically buys the one thing
runtime cannot: a specification that can never match is exactly the mistake a
green suite hides.

| Identifier | Reported when |
|---|---|
| `understudy.closure` | The closure specifies nothing, makes more than one call, or calls a static method a double cannot intercept |
| `understudy.cardinality` | `times(5, 2)`, a negative bound, `verify(…, never: true, times: 3)`, `times` beside a `minimum` |
| `understudy.matcher` | A matcher whose kind the parameter can never accept: `Arg::int()` where a `string` is declared |
| `understudy.returns` | `returns()` on a method declared `void`, where no value is ever observed |
| `understudy.matcherLeak` | A matcher written outside a specification, where it reaches the code as a value |

The rules are silent whenever they are not sure. A refined parameter type —
`non-empty-string`, an int range — answers "maybe" to its plain kind, and a
matcher can produce a value that fits it, so nothing is reported. A false
accusation costs more than a missed one here, because the engine still
catches at runtime what the extension misses.

To silence one of them, use its identifier:

```neon
parameters:
    ignoreErrors:
        - identifier: understudy.matcherLeak
```

## Why `understudy.matcherLeak` exists

Typing every matcher as `never` is what lets one stand in for a typed
parameter, and it does so everywhere — including in a real call:

```php
$repository->find(Arg::int());  // not a specification: the matcher is the argument
```

Without a rule for it the extension would be *weaker* than no extension for
that mistake, because PHPStan would otherwise have reported the argument
itself. Saying it directly is also better than the type error it replaces: at
runtime the matcher reaches the code as a sentinel object, and the failure it
eventually causes names neither the matcher nor the line.

## Security

The extension runs inside PHPStan, reads source and reflection, and reports.
It executes no code from the project under analysis and writes nothing.

## Examples

See [examples/README.md](examples/README.md). The executable demonstration is
the set of fixture projects under `tests/Integration/Fixtures`, each analysed
by a real PHPStan process as part of `composer build` — including a control
run with the extension switched off, which is what tells a working extension
apart from one that loads and does nothing.

## Development

```bash
make build          # validate + normalize + require-checker + cs + psalm + test + integration
make test           # unit suite
make test-coverage  # unit suite with coverage
make mutation       # mutation testing
make release-check  # build + rector + bc-check + mutation
```

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
