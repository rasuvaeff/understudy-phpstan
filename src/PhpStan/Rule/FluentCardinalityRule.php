<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
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

        if (!$this->isSpecificationChain($node->var)) {
            return [];
        }

        [$minimum, $maximum] = $this->timesBounds($node);
        $problem = Cardinality::timesProblem($minimum, $maximum);

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

    /**
     * Whether `->times()` sits anywhere on a specification chain, not only
     * directly on the verb.
     *
     * `expect(...)->returns('b')->times(5, 2)` is the spelling the engine's own
     * README recommends for a repeated call, and reading only the immediate
     * receiver found a `MethodCall` there and gave up — so of the four
     * spellings people write, the rule fired on one.
     */
    private function isSpecificationChain(Node\Expr $expression): bool
    {
        while ($expression instanceof MethodCall || $expression instanceof NullsafeMethodCall) {
            $expression = $expression->var;
        }

        return SpecificationExpr::verbOf($expression) !== null;
    }

    /**
     * The bounds of `times()`, by name where the call names them.
     *
     * `times(maximum: 5, minimum: 1)` is valid and means one to five calls;
     * read positionally it says `(5, 1)` and correct code was reported as
     * impossible — which costs more than the missed report above, because a
     * user cannot work around it.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function timesBounds(MethodCall $call): array
    {
        $minimum = null;
        $maximum = null;
        $position = 0;

        foreach ($call->getArgs() as $argument) {
            if ($argument->name instanceof Identifier) {
                match (strtolower($argument->name->toString())) {
                    'minimum' => $minimum = $this->literalInt($argument),
                    'maximum' => $maximum = $this->literalInt($argument),
                    default => null,
                };

                continue;
            }

            match ($position) {
                0 => $minimum = $this->literalInt($argument),
                1 => $maximum = $this->literalInt($argument),
                default => null,
            };

            ++$position;
        }

        return [$minimum, $maximum];
    }

    private function literalInt(?Arg $argument): ?int
    {
        $value = $argument?->value;

        return $value instanceof Int_ ? $value->value : null;
    }
}
