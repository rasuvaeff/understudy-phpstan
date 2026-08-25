<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Rasuvaeff\Understudy\PhpStan\Internal\Cardinality;
use Rasuvaeff\Understudy\PhpStan\Internal\SpecificationCheck;
use Rasuvaeff\Understudy\PhpStan\Internal\SpecificationExpr;

/**
 * `expect(...)->times($minimum, $maximum)` — the fluent form of a claim the
 * named arguments of `verify()` make too.
 *
 * @implements Rule<MethodCall>
 *
 * @internal
 */
final class FluentCardinalityRule implements Rule
{
    #[\Override]
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    #[\Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier
            || strtolower($node->name->toString()) !== 'times'
        ) {
            return [];
        }

        if (SpecificationExpr::verbOf($node->var) === null) {
            return [];
        }

        $arguments = array_values($node->getArgs());
        $problem = Cardinality::timesProblem(
            $this->literalInt($arguments[0] ?? null),
            $this->literalInt($arguments[1] ?? null),
        );

        if ($problem === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message('This understudy specification cannot work: ' . $problem)
                ->identifier(SpecificationCheck::CARDINALITY_IDENTIFIER)
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function literalInt(?Arg $argument): ?int
    {
        $value = $argument?->value;

        return $value instanceof Int_ ? $value->value : null;
    }
}
