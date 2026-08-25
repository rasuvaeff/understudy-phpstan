<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\VoidType;
use Rasuvaeff\Understudy\PhpStan\Internal\SpecificationExpr;
use Rasuvaeff\Understudy\PhpStan\Internal\SpecifiedCall;

/**
 * `returns()` on a method that returns nothing.
 *
 * The builder's generic parameter cannot carry this: `WhenBuilder<void>` is
 * not a claim anybody can satisfy, so the type is left alone (see
 * `Internal\BuilderType`) and the complaint is made here, where it can say
 * what to do instead.
 *
 * A specification of a void method is perfectly normal — it is `returns()`
 * beside one that is not, because nothing observes the value.
 *
 * @implements Rule<MethodCall>
 *
 * @internal
 */
final class VoidReturnsRule implements Rule
{
    public const string IDENTIFIER = 'understudy.returns';

    #[\Override]
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    #[\Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier
            || strtolower($node->name->toString()) !== 'returns'
        ) {
            return [];
        }

        $specification = $node->var;

        if (!$specification instanceof FuncCall && !$specification instanceof StaticCall) {
            return [];
        }

        $verb = SpecificationExpr::verbOf($specification);

        if ($verb !== 'when' && $verb !== 'expect') {
            return [];
        }

        $closure = $specification->getArgs()[0]->value ?? null;

        if ($closure === null) {
            return [];
        }

        $returnType = SpecifiedCall::of($closure, $scope)?->returnType();

        if (!$returnType instanceof VoidType) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'This understudy specification cannot work: the specified method returns void, '
                . 'so no value of it is ever observed. Drop returns(), or use answers() when the '
                . 'point is the side effect.',
            )
                ->identifier(self::IDENTIFIER)
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}
