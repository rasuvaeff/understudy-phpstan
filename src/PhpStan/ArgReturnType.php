<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use Rasuvaeff\Understudy\Arg;

/**
 * Makes a matcher pass for whatever the contract declares.
 *
 * `Arg::int()` is declared `mixed`, because a matcher has to be passable
 * wherever the contract declares anything at all. Inside a specification
 * closure that is exactly right, and PHPStan at level 9 reports it as
 * `mixed given` — a report that is correct about the type and wrong about
 * the code.
 *
 * `never` is the bottom type: every parameter accepts it, so the matcher
 * stops being a type error without a single diagnostic being suppressed.
 * That distinction is the whole point. A suppression hides everything on the
 * line; this hides nothing — a wrong argument beside the matcher, an
 * undefined method on the double and the statements around the closure all
 * keep their reports, verified by the fixture projects in
 * `tests/Integration/Fixtures`.
 *
 * The kind a matcher does promise is not encoded here on purpose. Typing
 * `Arg::string()` as `string` would make a `non-empty-string` parameter an
 * error, and a matcher can match a non-empty string perfectly well. The
 * impossible pairings are reported by `Rule\SpecificationCallRule` instead,
 * which asks whether ANY value of the kind fits and stays quiet on "maybe".
 *
 * @internal
 */
final class ArgReturnType implements DynamicStaticMethodReturnTypeExtension
{
    #[\Override]
    public function getClass(): string
    {
        return Arg::class;
    }

    #[\Override]
    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return true;
    }

    #[\Override]
    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): \PHPStan\Type\Type {
        return new NeverType();
    }
}
