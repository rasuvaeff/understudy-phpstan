<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Internal;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Type;

/**
 * The one call a specification closure describes, resolved against the
 * codebase.
 *
 * Null whenever anything is uncertain — a body that is not a single call, a
 * receiver whose type is unknown, a method nobody declares. Every caller
 * treats that as "stay quiet": the engine still catches at runtime what this
 * misses, and a false accusation costs more than a missed one.
 *
 * @internal
 */
final readonly class SpecifiedCall
{
    private function __construct(
        public MethodCall $call,
        private ExtendedMethodReflection $method,
        private Scope $scope,
    ) {}

    public static function of(Expr $closure, Scope $scope): ?self
    {
        $call = self::singleCall($closure);

        if (!$call instanceof \PhpParser\Node\Expr\MethodCall || !$call->name instanceof Identifier) {
            return null;
        }

        $calledOn = $scope->getType($call->var);
        $name = $call->name->toString();

        if (!$calledOn->hasMethod($name)->yes()) {
            return null;
        }

        return new self($call, $calledOn->getMethod($name, $scope), $scope);
    }

    /**
     * @return list<ParameterReflection>
     */
    public function parameters(): array
    {
        return $this->variant()->getParameters();
    }

    public function returnType(): Type
    {
        return $this->variant()->getReturnType();
    }

    private function variant(): ParametersAcceptor
    {
        return ParametersAcceptorSelector::selectFromArgs(
            $this->scope,
            $this->call->getArgs(),
            $this->method->getVariants(),
        );
    }

    private static function singleCall(Expr $closure): ?MethodCall
    {
        $body = match (true) {
            $closure instanceof ArrowFunction => $closure->expr,
            $closure instanceof Closure => self::onlyStatement($closure),
            default => null,
        };

        return $body instanceof MethodCall ? $body : null;
    }

    private static function onlyStatement(Closure $closure): ?Expr
    {
        if (\count($closure->stmts) !== 1) {
            return null;
        }

        $first = $closure->stmts[0];

        return $first instanceof Expression ? $first->expr : null;
    }
}
