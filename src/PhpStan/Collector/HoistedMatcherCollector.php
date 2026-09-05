<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Collector;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use Rasuvaeff\Understudy\PhpStan\Internal\MatcherName;

/**
 * Where a matcher is stored rather than passed.
 *
 * `$any = Arg::any();` and `$this->any = Arg::any();` hand the matcher to
 * nothing: they name it, and the name is used inside a specification later.
 * That later use is what `Rule\MatcherLeakRule` judges, and it judges by
 * textual range — so without this the assignment itself, which is nobody's
 * argument, was reported as a matcher reaching a real call.
 *
 * The range is the matcher's own, which is what the leak rule compares
 * against.
 *
 * @implements Collector<Assign, array{int, int}>
 *
 * @internal
 */
final class HoistedMatcherCollector implements Collector
{
    #[\Override]
    public function getNodeType(): string
    {
        return Assign::class;
    }

    #[\Override]
    public function processNode(Node $node, Scope $scope): ?array
    {
        $value = $node->expr;

        if (!$value instanceof Node\Expr\StaticCall || MatcherName::of($value) === null) {
            return null;
        }

        $start = $value->getStartFilePos();
        $end = $value->getEndFilePos();

        return $start <= $end ? [$start, $end] : [$end, $start];
    }
}
