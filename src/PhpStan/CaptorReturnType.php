<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use Rasuvaeff\Understudy\Captor;

/**
 * Makes a captor's `capture()` pass for whatever the contract declares.
 *
 * `Arg::captor()` hands back a `Captor`, and the capture site is a method
 * call on it — the one matcher {@see ArgReturnType} never sees, because that
 * extension covers the `Arg::` statics. The reasoning is the same: `never`
 * is the bottom type, every parameter accepts it, and nothing on the line is
 * suppressed.
 *
 * The same obligation follows too: a `capture()` leaked into a real call
 * would go silent under this typing, so `Rule\MatcherLeakRule` reports it
 * there — without that rule this extension would be weaker than none.
 *
 * @internal
 */
final class CaptorReturnType implements DynamicMethodReturnTypeExtension
{
    #[\Override]
    public function getClass(): string
    {
        return Captor::class;
    }

    #[\Override]
    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'capture';
    }

    #[\Override]
    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type {
        return new NeverType();
    }
}
