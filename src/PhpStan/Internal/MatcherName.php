<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Internal;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Rasuvaeff\Understudy\Arg;

/**
 * The `Arg::` method name, when an expression is a matcher.
 *
 * Matched on the resolved class rather than on the short name: `MyArg::int()`
 * is somebody else's method, and reading `Arg` off the end of a name would
 * claim it as ours.
 *
 * @internal
 */
final class MatcherName
{
    private function __construct() {}

    /**
     * @return non-empty-string|null
     */
    public static function of(Expr $expression): ?string
    {
        if (!$expression instanceof StaticCall
            || !$expression->class instanceof Name
            || !$expression->name instanceof Identifier
        ) {
            return null;
        }

        if (strtolower(ltrim(ResolvedName::of($expression->class), '\\')) !== strtolower(Arg::class)) {
            return null;
        }

        return $expression->name->toString();
    }
}
