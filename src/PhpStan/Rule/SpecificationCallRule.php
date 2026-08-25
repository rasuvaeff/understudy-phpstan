<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use Rasuvaeff\Understudy\PhpStan\Internal\SpecificationCheck;
use Rasuvaeff\Understudy\PhpStan\Internal\SpecificationExpr;

/**
 * The free-function form: `when()`, `expect()`, `verify()`.
 *
 * @implements Rule<FuncCall>
 *
 * @internal
 */
final class SpecificationCallRule implements Rule
{
    #[\Override]
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    #[\Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $verb = SpecificationExpr::verbOf($node);

        return $verb === null ? [] : SpecificationCheck::errors($node, $verb, $scope);
    }
}
