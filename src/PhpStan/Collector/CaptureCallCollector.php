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

        if (!(new ObjectType(Captor::class))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return null;
        }

        return [$node->getStartFilePos(), $node->getStartLine()];
    }
}
