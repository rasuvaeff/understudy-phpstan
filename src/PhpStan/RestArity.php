<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;
use Rasuvaeff\Understudy\PhpStan\Internal\MatcherName;

/**
 * Lets a specification stop before the contract's required parameters run
 * out, the way `Arg::rest()` says it may.
 *
 * `when(fn () => $storage->recordOutcome('svc', Arg::rest()))` physically
 * passes two arguments to a seven-parameter method — the engine's generated
 * parameters carry a sentinel default exactly so that call can be made — and
 * PHPStan's `arguments.count` report is correct about the contract and wrong
 * about the idiom.
 *
 * Ignored wherever the call's last written argument is `Arg::rest()`, with
 * no specification-scope test, and that is a reasoned omission rather than a
 * shortcut: this extension is handed the error's node and scope, not the
 * collected specification ranges, and it does not need them — a `rest()` in
 * a real call is already reported by `Rule\MatcherLeakRule` (and answered at
 * runtime with `ArgumentCountError`), so silencing the arity half there
 * hides nothing that is not louder elsewhere.
 *
 * @internal
 */
final class RestArity implements IgnoreErrorExtension
{
    private const string ARITY = 'arguments.count';

    #[\Override]
    public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
    {
        if ($error->getIdentifier() !== self::ARITY) {
            return false;
        }

        if (!$node instanceof CallLike || $node->isFirstClassCallable()) {
            return false;
        }

        $arguments = $node->getArgs();

        if ($arguments === []) {
            return false;
        }

        return MatcherName::of($arguments[count($arguments) - 1]->value) === 'rest';
    }
}
