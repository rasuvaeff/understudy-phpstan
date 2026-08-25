# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

- Initial development: the PHPStan extension of the understudy family.
  Matchers are typed `never` so one may stand in for a declared parameter at
  level 9 and above without a single diagnostic being suppressed; `when()`
  and `expect()` get the builder type they really produce, so `returns()` and
  `answers()` are checked against the method being specified;
  `Understudy::wire()` gets the shape read off the constructor; and five
  rules report specifications that cannot work — `understudy.closure`,
  `understudy.cardinality`, `understudy.matcher`, `understudy.returns` and
  `understudy.matcherLeak`.
