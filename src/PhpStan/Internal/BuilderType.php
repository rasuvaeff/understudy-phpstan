<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Internal;

use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use PHPStan\Type\VoidType;

/**
 * The builder type a specification really produces.
 *
 * The core declares `when(): WhenBuilder<mixed>`, and it has no choice: the
 * method being specified is only known from the closure that makes the call.
 * With `mixed` the template parameter buys nothing, so `returns('oops')` on a
 * `: ?Book` method type-checks.
 *
 * Filling the parameter in is all this needs to do. `WhenBuilder<TReturn>`
 * already declares `returns(TReturn ...$values)` and
 * `answers(callable(Invocation): TReturn)`, so once the parameter is real,
 * PHPStan checks both on its own — there is no rule of ours to get wrong.
 *
 * @internal
 */
final class BuilderType
{
    private function __construct() {}

    /**
     * @param array<Arg>       $arguments the specification call's arguments
     * @param class-string     $builder   the builder class to parameterise
     */
    public static function of(array $arguments, string $builder, Scope $scope): ?Type
    {
        $closure = $arguments[0]->value ?? null;

        if ($closure === null) {
            return null;
        }

        $returnType = SpecifiedCall::of($closure, $scope)?->returnType();

        if (!$returnType instanceof \PHPStan\Type\Type || self::isUseless($returnType)) {
            return null;
        }

        return new GenericObjectType($builder, [$returnType]);
    }

    /**
     * A parameter worth filling in has to say more than the core already
     * does, and has to be a value at all.
     *
     * `mixed` is what the core declares anyway. `void` and `never` are the
     * ones that would actively mislead: `WhenBuilder<void>::returns(void
     * ...$values)` is not a claim anybody can satisfy, and a specification of
     * a void method is a normal thing to write. The complaint that belongs
     * there is a rule of ours, not a generic parameter nobody can fill.
     */
    private static function isUseless(Type $returnType): bool
    {
        return $returnType instanceof MixedType
            || $returnType instanceof VoidType
            || $returnType instanceof NeverType;
    }
}
