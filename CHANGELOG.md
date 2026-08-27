# Changelog

## Unreleased

- Allow `rasuvaeff/understudy` `^0.3` (the engine refuses colliding same-call
  `when()`/`expect()` registrations with `ConflictingExpectation` from 0.3.0;
  nothing in this adapter changes behaviour).

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
