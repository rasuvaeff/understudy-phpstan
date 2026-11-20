<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use Rasuvaeff\Understudy\PhpStan\Internal\SpecificationCheck;
use Rasuvaeff\Understudy\PhpStan\Internal\SpecificationExpr;

/**
 * The free-function form: `when()`, `expect()`, `expectSequence()`, `verify()`.
 *
 * @implements Rule<FuncCall>
 *
 * @internal
 */
final readonly class SpecificationCallRule implements Rule
{
    public function __construct(private ReflectionProvider $reflection) {}

    #[\Override]
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    #[\Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $verb = SpecificationExpr::verbOf($node);

        return $verb === null ? [] : SpecificationCheck::errors($node, $verb, $scope, $this->reflection);
    }
}
