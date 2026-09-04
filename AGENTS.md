# AGENTS.md — understudy-phpstan

Guidance for AI agents working on this package. Read before changing code.

## What this is

The PHPStan extension of the understudy family. It makes a call-closure
specification analysable: a matcher fits whatever the contract declares,
`returns()` is checked against the method being specified, `wire()` has the
shape of the class it wired, and specifications that cannot work are
reported.

Namespace `Rasuvaeff\Understudy\PhpStan`. Everything in `src/` is
`@internal`: the package has no PHP surface at all. What a project touches is
`extension.neon` — pulled in by `phpstan/extension-installer` — and the error
identifiers documented in the README.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count. The integration suite runs
   real PHPStan processes and is part of that gate.
2. **No suppressions.** No `@psalm-suppress`, no baseline, and no
   `ignoreErrors` of our own in any fixture. Fix the root cause.
3. **A matcher is typed, never suppressed.** `ArgReturnType` answers `never`
   — the bottom type every parameter accepts — and that is the whole
   mechanism. It is narrow by construction: only the matcher's own argument
   stops being an error, and a wrong argument beside it, an undefined method
   on the double, and the statements around the closure all keep their
   reports. Any change here must keep `Fixtures/Matchers/src/Wrong.php`
   reporting exactly what it reports today. Reaching for a blanket
   `ignoreErrors` instead would hide the mistakes this extension exists to
   surface.
4. **Preserve the public contract.** An identifier is the contract: renaming
   one breaks every `ignoreErrors` entry pointing at it. Update README,
   README.ru and llms.txt with any change to what is reported.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make build
make cs-fix
make psalm
make test
make test-coverage
make mutation
make release-check
```

`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## Invariants & gotchas

- **The kind a matcher promises is deliberately absent from its type.**
  Typing `Arg::string()` as `string` would report a `non-empty-string`
  parameter as an error, and a matcher can match a non-empty string
  perfectly well. Impossible pairings are `MatcherKind`'s job, and it asks
  PHPStan's type algebra whether ANY value of the kind fits — reporting only
  a definite "no", silent on "maybe".
- **The leak rule is load-bearing, not an extra.** Typing matchers as `never`
  works everywhere, including in a real call, where PHPStan would otherwise
  have reported the argument. Remove `MatcherLeakRule` and the extension
  becomes worse than no extension for that mistake.
- **Node visit order is not a contract.** PHPStan hands a rule a node and a
  scope and nothing above it, and the order nodes arrive in is its own
  business — measured here, an `Arg::` call inside a `when()` argument
  arrived AFTER the `when()` itself, but a static index built on that would
  be right or empty by luck. The leak rule uses collectors, whose data is
  keyed by file and survives the result cache.
- **`ParametersAcceptorSelector::selectSingle()` does not exist in PHPStan
  2.x.** Use `selectFromArgs()` where there are arguments to select by, and
  the first variant where there are none (a constructor reached through
  `wire()`).
- **`Type::describe()` on an object type needs a running analysis.** It asks
  for the reflection provider through a static accessor, which is only set
  inside PHPStan. A unit test that builds an `ObjectType` and expects a
  message will die on it — that pairing belongs in a fixture project, and
  `MatcherKindTest` says so where the case would have gone.
- **The `phpstan/phpstan` constraint is `^2.2.2`, and that is what can be
  proved.** `rector/rector` requires `^2.2.2` itself, so nothing in this
  package's dev graph — `composer build`, `Prefer lowest`, the integration
  suite — can ever install 2.1.x. A wider constraint would be a promise no
  gate here exercises. Widening it later is a minor; narrowing it would be a
  major, so it starts where the evidence is.
- **`composer-require-checker` cannot see inside the phar either.** Every
  `PHPStan\*` symbol is listed in `composer-require-checker.json`, not
  because the dependency is missing — `phpstan/phpstan` is in `require` — but
  because the checker maps symbols to packages by scanning their files, and
  there are none to scan. A new PHPStan class referenced in `src/` code fails
  the gate until it is added to that list; one that appears only in an
  annotation does not trip it, so list those when they are first referenced
  either way — the list is the map, not the gate.
- **Psalm can analyse this package even though PHPStan ships as a phar.** The
  `phpstan/phpstan` package registers an autoloader that loads classes out of
  `phpstan.phar`, and Psalm resolves them through it. `findUnusedCode` stays
  `false`: the rules are named in neon, and nothing in PHP references them.
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types.
- `examples/` is part of the public contract: keep scripts runnable and update
  `examples/README.md` when example usage changes. This package has none —
  the fixture projects are its executable demonstration.
- **Every gate here reads the working tree; a consumer downloads
  `git archive`.** Between the two there is `export-ignore` and nothing else, so
  a file that should not ship reaches users without reddening anything. The
  `Consumer smoke` job installs a dist archive of the commit into a throwaway
  project, takes the engine from Packagist the way a user does, and drives `extra.phpstan.includes`, which nothing else exercises at all.
  The script itself lives in the core repository (`understudy/bin/consumer-smoke`) —
  one copy, checked out by this workflow; run the whole family from local
  checkouts with `bin/understudy-consumer-smoke` in the workspace repository.
- **CI workflows are SHA-pinned.** Every `uses:` in `.github/workflows/*.yml`
  references a 40-char commit SHA with a `# vN` trailing comment
  (e.g. `actions/checkout@<sha> # v4`). Never revert to floating `@vN` tags.
  Updates go through Dependabot, which bumps the SHA and preserves the comment.
  Workflows also carry `permissions: { contents: read }` at workflow level and
  `persist-credentials: false` on every `actions/checkout` step. Verify with
  `zizmor --persona=auditor .github/` — must report no `unpinned-uses`,
  `excessive-permissions`, or `artipacked` findings.

## When you finish

- Update `README.md` **and `README.ru.md`** (both languages, same commit;
  and `llms.txt` when an identifier or a rule changes); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build`; if the change affects public API or release safety,
  also run `make release-check`. Paste the output.
