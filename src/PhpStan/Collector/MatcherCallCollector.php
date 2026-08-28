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
 * @implements Collector<StaticCall, array{int, string}>
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

        // `Arg::captor()` is the one `Arg::` call that is NOT a matcher: it
        // builds the captor, legitimately outside any specification. The
        // matcher it leads to is `->capture()`, which has a collector of its
        // own.
        if (strtolower($matcher) === 'captor') {
            return null;
        }

        return [$node->getStartLine(), $matcher];
    }
}
