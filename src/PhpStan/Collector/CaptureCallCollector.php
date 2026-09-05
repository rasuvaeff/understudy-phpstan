<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Collector;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ObjectType;
use Rasuvaeff\Understudy\Captor;

/**
 * Where every `$captor->capture()` is written.
 *
 * The receiver's type is what decides — a foreign `capture()` on some other
 * object is not a matcher, and reporting it as a leak would be a false
 * accusation in a consumer's own code. Type-checked here, where a Scope is
 * available; the leak rule that reads this out only sees locations.
 *
 * @implements Collector<MethodCall, array{int, int}>
 *
 * @internal
 */
final class CaptureCallCollector implements Collector
{
    #[\Override]
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    #[\Override]
    public function processNode(Node $node, Scope $scope): ?array
    {
        if (!$node->name instanceof Identifier || $node->name->toLowerString() !== 'capture') {
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

        if (!(new ObjectType(Captor::class))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return null;
        }

        return [$node->getStartFilePos(), $node->getStartLine()];
    }
}
