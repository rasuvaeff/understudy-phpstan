<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Internal;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * Whether an expression is a specification call, and which verb it is.
 *
 * Both spellings answer here — the free function a test file imports and the
 * collision-free `Understudy::` form — so that no rule has to know there are
 * two.
 *
 * @internal
 */
final class SpecificationExpr
{
    private function __construct() {}

    /**
     * The verb in lower case, or null when this is somebody else's call.
     */
    public static function verbOf(Expr $expression): ?string
    {
        if ($expression instanceof FuncCall) {
            if (!$expression->name instanceof Name) {
                return null;
            }

            $name = ResolvedName::of($expression->name);

            return VerbNames::isFunction($name) ? VerbNames::shortVerb($name) : null;
        }

        if (!$expression instanceof StaticCall
            || !$expression->class instanceof Name
            || !$expression->name instanceof Identifier
        ) {
            return null;
        }

        $verb = $expression->name->toString();

        return VerbNames::isStaticCall(ResolvedName::of($expression->class), $verb)
            ? strtolower($verb)
            : null;
    }
}
