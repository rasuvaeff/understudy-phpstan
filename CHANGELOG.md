# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

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
