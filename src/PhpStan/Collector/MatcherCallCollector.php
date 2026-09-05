<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Collector;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use Rasuvaeff\Understudy\PhpStan\Internal\MatcherName;

/**
 * Where every `Arg::` matcher is written.
 *
 * @implements Collector<StaticCall, array{int, int, string}>
 *
 * @internal
 */
final class MatcherCallCollector implements Collector
{
    #[\Override]
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    #[\Override]
    public function processNode(Node $node, Scope $scope): ?array
    {
        $matcher = MatcherName::of($node);

        if ($matcher === null) {
            return null;
        }

        // A matcher written inside a closure has not reached anything yet:
        // the body runs when somebody calls it, and if that somebody is the
        // engine the closure IS the specification. A specification hoisted
        // into a variable, returned from a helper or handed over as a
        // `callable` never falls inside the textual range of the `when()`
        // that runs it, and calling that a leak reported working code.
        if ($scope->isInAnonymousFunction()) {
            return null;
        }

        // `Arg::captor()` is the one `Arg::` call that is NOT a matcher: it
        // builds the captor, legitimately outside any specification. The
        // matcher it leads to is `->capture()`, which has a collector of its
        // own.
        if (strtolower($matcher) === 'captor') {
            return null;
        }

        return [$node->getStartFilePos(), $node->getStartLine(), $matcher];
    }
}
