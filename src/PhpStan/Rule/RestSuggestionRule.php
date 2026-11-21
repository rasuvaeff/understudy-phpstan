<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use Rasuvaeff\Understudy\PhpStan\Internal\SpecificationCheck;
use Rasuvaeff\Understudy\PhpStan\Internal\SpecificationExpr;

/**
 * `understudy.rest`: a tail of `Arg::any()` that `Arg::rest()` would say in
 * one word, under every verb — the free-function and the static form alike.
 *
 * Opt-in. PHPStan has no severity below an error, and a suggestion reported
 * as one would turn a green run red on a minor upgrade, so `extension.neon`
 * registers this rule only under `understudy.suggestRest: true`.
 *
 * @implements Rule<CallLike>
 *
 * @internal
 */
final readonly class RestSuggestionRule implements Rule
{
    #[\Override]
    public function getNodeType(): string
    {
        return CallLike::class;
    }

    #[\Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall && !$node instanceof StaticCall) {
            return [];
        }

        return SpecificationExpr::verbOf($node) === null ? [] : SpecificationCheck::restSuggestions($node, $scope);
    }
}
