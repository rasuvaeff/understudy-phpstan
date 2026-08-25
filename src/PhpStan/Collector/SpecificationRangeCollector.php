<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Collector;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use Rasuvaeff\Understudy\PhpStan\Internal\SpecificationExpr;

/**
 * The lines every specification call spans.
 *
 * Collected rather than read from a parent node because PHPStan hands a rule
 * a node and a scope and nothing above it, and the order nodes arrive in is
 * not part of its contract — a static index filled as the analysis walks
 * would be right or empty depending on that order. Collected data is keyed
 * by file and survives the result cache, so `Rule\MatcherLeakRule` can ask
 * its question once every file has answered.
 *
 * @implements Collector<CallLike, array{int, int}>
 *
 * @internal
 */
final class SpecificationRangeCollector implements Collector
{
    #[\Override]
    public function getNodeType(): string
    {
        return CallLike::class;
    }

    #[\Override]
    public function processNode(Node $node, Scope $scope): ?array
    {
        if (SpecificationExpr::verbOf($node) === null) {
            return null;
        }

        $start = $node->getStartLine();
        $end = $node->getEndLine();

        return $start <= $end ? [$start, $end] : [$end, $start];
    }
}
