<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use Rasuvaeff\Understudy\PhpStan\Internal\SpecificationCheck;
use Rasuvaeff\Understudy\PhpStan\Internal\SpecificationExpr;

/**
 * The static form, `Understudy::when()` and the call-closure readers that
 * have no free-function spelling: `calls()`, `lastCall()`,
 * `verifySequence()`.
 *
 * @implements Rule<StaticCall>
 *
 * @internal
 */
final class SpecificationStaticCallRule implements Rule
{
    #[\Override]
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    #[\Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $verb = SpecificationExpr::verbOf($node);

        return $verb === null ? [] : SpecificationCheck::errors($node, $verb, $scope);
    }
}
