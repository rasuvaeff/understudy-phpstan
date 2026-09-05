# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.5.0 — 2026-09-05

- Requires `rasuvaeff/understudy` `^0.8`, and requires it as a single term. The
  accumulating union it carried (`^0.4 || ^0.5 || …`) had to be widened by hand
  on every core release, and a package that misses one becomes uninstallable
  beside its own engine.
- **`->returns(...)->times(5, 2)` is reported as impossible again.** The
  cardinality rule read only the immediate receiver of `times()`, so it fired
  on `expect(...)->times(5, 2)` and stayed silent on every spelling with a link
  in between — including the one the engine's own README recommends for a
  repeated call.
- **`times(maximum: 5, minimum: 1)` is no longer reported as impossible.** The
  bounds were read positionally, so a named call with the arguments in the
  other order looked like `(5, 1)`. Correct code turned red, with no way around
  it but removing the extension.
- **A specification that reaches its double through a helper is silent.**
  `when(fn () => $this->gate()->find(1))`, `$double->find($this->id())` and
  `$this->passThrough($double->find(1))` were all reported as "the closure
  makes 2 calls". The engine throws on the first call that lands on a double
  and never sees the rest, so a call that is the receiver of another, or one
  made on `$this`, is not a second specified call. The total still answers "no
  call at all", so a specification that reaches its double only through a
  helper is not accused of specifying nothing either.
- **`understudy.matcherLeak` no longer accuses a matcher that arrives
  indirectly.** The rule compares file offsets against the textual range of the
  `when()` call, so a matcher hoisted into a variable, stored on a property or
  written inside a closure handed over later fell outside every range and was
  called a leak. A matcher inside any closure is now left alone — the closure
  has not run — and a matcher that is assigned rather than passed is nobody's
  argument.
- **`wire()` builds a shape for a `?Contract` parameter.** Null in the union
  answered no class name, so no shape was built for the key and
  `$wired['doubles']['clock']` stayed `object` — "Call to an undefined method
  object::now()" on working code, against a docblock promising that `?Contract`
  is the same double.
- Includes the matcher-location and returned-call fixes merged after 0.4.1
  (#23), which had not been released.

## 0.4.1 — 2026-09-04

- **Documentation review fixes.** Requirements now lists the direct
  `nikic/php-parser` dependency; llms.txt names the engine constraint. The
  Usage section is titled «Usage» like the rest of the family. AGENTS.md's
  require-checker rule states what the gate really catches: symbols referenced
  in code, not annotation-only ones.

## 0.4.0 — 2026-09-04

A minor rather than a patch: `verify($call, times: -1)` is a report the
extension did not make before, so a consumer's own code can draw a diagnostic
it did not draw yesterday.

- Allow `rasuvaeff/understudy` `^0.7`. Widened rather than raised.
- `verify($call, times: -1)` is reported. `verifyProblem()` fell through to
  the `minimum`/`maximum` pair and dropped `times` on the way, so a negative
  exact count reached no check at all. It has a unit test rather than a
  fixture on purpose: `verify()` declares `int<0, max>|null`, so wherever
  PHPStan checks that annotation it reports the argument itself and this rule
  never speaks — what the rule buys is the levels where it does not, and these
  rules run at every level, including 0.
- `RestArity` says why it differs from the Psalm sibling on `Arg::rest()`.
  Both packages answer the same question and neither is a shortcut: there a
  leaked matcher has no rule of its own, so the narrower scope is what keeps
  the diagnostic alive; here `Rule\MatcherLeakRule` says it louder.
- The `VerbNames` docblock carries the history its Psalm counterpart had —
  the `calls()` closure reported as a leak while dogfooding, and `lastCall()`
  silently outside every rule. The two copies had drifted apart, and the
  lesson is the same in both.

## 0.3.0 — 2026-09-04

A minor rather than a patch: the misuse rules now fire inside
`expectSequence()`, where they were silently absent, so a consumer's own code
can draw a diagnostic it did not draw before.

- Allow `rasuvaeff/understudy` `^0.6`. Widened rather than raised: the
  extension works against 0.4, 0.5 and 0.6, and consumers on the older ones
  should not be cut off from it.

- **`wire()`'s shape disagreed with the core on a parameter naming more than
  one contract.** An intersection — `BookRepository&Auditor` — is ONE double
  standing for both, which is what `Wire::resolve()` builds; the extension
  dropped the parameter, so its key was reported as a missing offset on code
  that wires and runs. A union of two object types makes the core refuse the
  class outright (`CannotWire`), and reporting a missing key there named the
  wrong mistake: no shape describes a call that never returns, so none is
  produced. Fixes #16.

- The mutation gate rises from 95 to 97. Re-measured on PHP 8.4, the version
  the coverage job pins: 188 of 191 mutants killed, 98.43%, with the same
  three equivalent survivors the config already names. The gate is one mutant
  below the measurement rather than a round number three points under it.

- `expectSequence()` is recognised as a specification verb. It was known to
  neither spelling's list, so `SpecificationRangeCollector` never recorded the
  call and `understudy.matcherLeak` was reported for every matcher inside an
  armed protocol — a false report on code the engine accepts. The closure and
  matcher-kind rules were silently absent on the same closures. Fixes #13.
- `VerbNamesTest` now walks the core's own public surface and fails when a
  closure-taking verb is missing from either list. `expectSequence()` was the
  second verb to fall outside every rule after `lastCall()`; a list nobody
  checks is what let both happen.
- Both READMEs say what was only implied: the `understudy.*` identifiers are
  stable, because they are what a consumer writes into `ignoreErrors`, while
  the wording of a message is not.

## 0.2.1 — 2026-09-03

- The Requirements section of both READMEs said `rasuvaeff/understudy` `^0.1`
  while `composer.json` has required `^0.4` since 0.2.0.
- Allow `rasuvaeff/understudy` `^0.5`. Widened rather than raised: the
  extension works against both, and 0.4 consumers should not be cut off from
  it.

## 0.2.0 — 2026-08-28

A minor rather than a patch: new behaviour toward the consumer's own code
(an ignored arity report, a newly typed matcher) and a raised dependency
floor are both boundaries Composer's caret already treats as breaking on 0.x.

- The `rasuvaeff/understudy` floor rises to `^0.4`: the fixtures that prove
  the new behaviour are written in the 0.4 idioms, and a lowest-versions run
  against 0.1 would be proving nothing. Consumers on an older understudy stay
  on the 0.1.x line of this package.
- **understudy 0.4 idioms** (rasuvaeff/understudy-phpstan#6). `Arg::rest()`:
  the `arguments.count` report is ignored (via an `IgnoreErrorExtension`)
  wherever a call's last written argument is `Arg::rest()`; in a real call
  the line is reported once, as `understudy.matcherLeak` — the leak is the
  actual mistake, and the engine answers the call with `ArgumentCountError`.
  `Arg::captor()`: `$captor->capture()` is typed `never` like the `Arg::`
  factories when the receiver is a `Captor`, no longer counts against
  "exactly one call per closure", and is reported by `understudy.matcherLeak`
  when it reaches a real call; `Arg::captor()` itself stopped being collected
  as a matcher — it is the factory, legitimately outside any specification,
  and was falsely reported as a leak.

## 0.1.3 — 2026-08-28

- Allow `rasuvaeff/understudy` `^0.4`: `Arg::rest()`, `Arg::captor()`,
  `Understudy::delegate()`, `Understudy::lean()` and rendered property hooks
  are all additive — the adapter needs no code change.

## 0.1.2 — 2026-08-27

- Allow `rasuvaeff/understudy` `^0.3` (the engine refuses colliding same-call
  `when()`/`expect()` registrations with `ConflictingExpectation` from 0.3.0;
  nothing in this adapter changes behaviour).

## 0.1.1 — 2026-08-27

- Accept `rasuvaeff/understudy` 0.2 alongside 0.1. Nothing in the adapter
  changes; the core's 0.2.0 is additive, and on 0.x Composer's caret treats a
  minor as a boundary, so the constraint has to say so explicitly. Widening it
  breaks no existing install.

- **The release workflow waits for the matrix build instead of judging it
  mid-flight.** A tag pushed right after the merge arrived while master's own
  build was still running, and the guard read a `null` conclusion as a failed
  one, refusing to create the GitHub Release. Hit for real on the core package
  while tagging `v0.1.1`. No effect on the package itself.

## 0.1.0 — 2026-08-25

- Initial development: the PHPStan extension of the understudy family.
  Matchers are typed `never` so one may stand in for a declared parameter at
  level 9 and above without a single diagnostic being suppressed; `when()`
  and `expect()` get the builder type they really produce, so `returns()` and
  `answers()` are checked against the method being specified;
  `Understudy::wire()` gets the shape read off the constructor; and five
  rules report specifications that cannot work — `understudy.closure`,
  `understudy.cardinality`, `understudy.matcher`, `understudy.returns` and
  `understudy.matcherLeak`.
