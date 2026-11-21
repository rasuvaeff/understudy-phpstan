<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Internal;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Rasuvaeff\Understudy\Captor;

/**
 * Says what the engine would say, before the test runs.
 *
 * Every complaint here has a runtime counterpart — `InvalidCallSpecification`
 * for a closure that specifies nothing, an unsatisfiable cardinality, an
 * expectation that can never match. Reporting them statically buys the one
 * thing runtime cannot: the mistake is visible without running the suite,
 * and a specification that can never match is exactly the mistake a green
 * suite hides.
 *
 * Silent whenever it is not sure. A false accusation costs more than a
 * missed one here, because the engine still catches what this misses.
 *
 * @internal
 */
final class SpecificationCheck
{
    public const string CLOSURE_IDENTIFIER = 'understudy.closure';

    public const string CARDINALITY_IDENTIFIER = 'understudy.cardinality';

    public const string MATCHER_IDENTIFIER = 'understudy.matcher';

    public const string REST_IDENTIFIER = 'understudy.rest';

    private function __construct() {}

    /**
     * @param string $verb the verb in lower case, without its namespace
     *
     * @return list<IdentifierRuleError>
     */
    public static function errors(
        FuncCall|StaticCall $call,
        string $verb,
        Scope $scope,
        ReflectionProvider $reflection,
    ): array {
        $arguments = array_values($call->getArgs());
        $closures = self::closuresOf($arguments);

        if ($closures === []) {
            return [];
        }

        $errors = [];

        foreach ($closures as $closure) {
            $problem = ClosureShape::of($closure)->problem();

            if ($problem !== null) {
                $errors[] = self::error($problem, self::CLOSURE_IDENTIFIER, $closure->getStartLine());
            }
        }

        if ($verb === 'verify') {
            $problem = Cardinality::verifyProblem(Cardinality::namedLiterals($arguments));

            if ($problem !== null) {
                $errors[] = self::error($problem, self::CARDINALITY_IDENTIFIER, $call->getStartLine());
            }
        }

        foreach ($closures as $closure) {
            foreach (self::matcherErrors($closure, $scope, $reflection) as $error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * A matcher whose kind no argument of that parameter could ever have.
     *
     * @return list<IdentifierRuleError>
     */
    private static function matcherErrors(Expr $closure, Scope $scope, ReflectionProvider $reflection): array
    {
        $specified = SpecifiedCall::of($closure, $scope);

        if (!$specified instanceof SpecifiedCall) {
            return [];
        }

        $parameters = $specified->parameters();
        $errors = [];

        foreach (array_values($specified->call->getArgs()) as $position => $argument) {
            $parameter = $parameters[$position] ?? null;

            if ($parameter === null) {
                continue;
            }

            $problem = self::matcherProblem($argument->value, $parameter->getType(), $scope, $reflection);

            if ($problem !== null) {
                $errors[] = self::error(
                    $problem,
                    self::MATCHER_IDENTIFIER,
                    $argument->value->getStartLine(),
                );
            }
        }

        return $errors;
    }

    /**
     * A tail of `Arg::any()` that `Arg::rest()` would say in one word, in
     * every closure of the specification.
     *
     * Apart from {@see errors()} because it is a suggestion rather than a
     * mistake, and opt-in for that reason: `Rule\RestSuggestionRule` is
     * registered only under `understudy.suggestRest: true`.
     *
     * @return list<IdentifierRuleError>
     */
    public static function restSuggestions(FuncCall|StaticCall $call, Scope $scope): array
    {
        $errors = [];

        foreach (self::closuresOf(array_values($call->getArgs())) as $closure) {
            $specified = SpecifiedCall::of($closure, $scope);

            if ($specified instanceof SpecifiedCall) {
                foreach (self::restErrors($specified, $specified->parameters()) as $error) {
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * Every closure argument, not the first one: `verifySequence()` takes a
     * whole protocol, and a mistake in its third step is the same mistake as
     * in its first. The non-closure arguments of `verify()` are the
     * cardinality.
     *
     * @param list<Arg> $arguments
     *
     * @return list<Expr>
     */
    private static function closuresOf(array $arguments): array
    {
        return array_values(array_filter(
            array_map(static fn(Arg $argument): Expr => $argument->value, $arguments),
            static fn(Expr $value): bool => $value instanceof Closure || $value instanceof ArrowFunction,
        ));
    }

    /**
     * The suggestion, on the first `Arg::any()` of the run. Its own
     * identifier and no "cannot work" preamble, because nothing here is
     * broken.
     *
     * @param list<ParameterReflection> $parameters
     *
     * @return list<IdentifierRuleError>
     */
    private static function restErrors(SpecifiedCall $specified, array $parameters): array
    {
        $required = 0;

        foreach ($parameters as $parameter) {
            $required += $parameter->isOptional() ? 0 : 1;
        }

        $arguments = array_values($specified->call->getArgs());
        $tail = RestTail::of(
            $arguments,
            static fn(Expr $value): ?string => MatcherName::of($value),
            $required,
            count($parameters),
            $specified->isVariadic(),
        );

        if ($tail === null) {
            return [];
        }

        [$start, $length] = $tail;

        return [
            RuleErrorBuilder::message(RestTail::message($length))
                ->identifier(self::REST_IDENTIFIER)
                ->line($arguments[$start]->value->getStartLine())
                ->build(),
        ];
    }

    private static function matcherProblem(
        Expr $argument,
        Type $parameter,
        Scope $scope,
        ReflectionProvider $reflection,
    ): ?string {
        $unrelated = static fn(string $one, string $other): bool => self::unrelatedClasses($reflection, $one, $other);

        $captured = self::capturedClass($argument, $scope);

        if ($captured !== null) {
            return MatcherKind::instanceProblem('`capture()`', $captured, $parameter, $unrelated);
        }

        $matcher = MatcherName::of($argument);

        if ($matcher === null || !$argument instanceof StaticCall) {
            return null;
        }

        if (strtolower($matcher) !== 'instanceof') {
            return MatcherKind::problem($matcher, $parameter);
        }

        $class = self::literalClass($argument->getArgs()[0]->value ?? null);

        return $class === null
            ? null
            : MatcherKind::instanceProblem('`Arg::' . $matcher . '()`', $class, $parameter, $unrelated);
    }

    /**
     * The class a captor's `capture()` holds instances of, read off the
     * receiver's resolved type: our `Captor` with a class for its template.
     * A namesake `capture()` or an untyped captor answers null.
     *
     * @return non-empty-string|null
     */
    private static function capturedClass(Expr $argument, Scope $scope): ?string
    {
        if (!$argument instanceof MethodCall
            || !$argument->name instanceof Identifier
            || $argument->name->toLowerString() !== 'capture'
            || $argument->getArgs() !== []
        ) {
            return null;
        }

        $receiver = $scope->getType($argument->var);

        if ($receiver->getObjectClassNames() !== [Captor::class]) {
            return null;
        }

        $held = $receiver->getTemplateType(Captor::class, 'T');

        if (!$held instanceof ObjectType) {
            return null;
        }

        $class = $held->getClassName();

        return $class === '' ? null : $class;
    }

    /**
     * `Foo::class`, written out — the one spelling whose class is known
     * without guessing. `self::class`, `static::class` and a variable are
     * left alone.
     *
     * @return non-empty-string|null
     */
    private static function literalClass(?Expr $expression): ?string
    {
        if (!$expression instanceof ClassConstFetch
            || !$expression->class instanceof Name
            || !$expression->name instanceof Identifier
            || $expression->name->toLowerString() !== 'class'
            || $expression->class->isSpecialClassName()
        ) {
            return null;
        }

        $class = ltrim(ResolvedName::of($expression->class), '\\');

        return $class === '' ? null : $class;
    }

    /**
     * Two classes neither of which extends the other — no object is an
     * instance of both. An interface, a trait or a name PHPStan does not know
     * is never unrelated: the answer there is a maybe.
     */
    private static function unrelatedClasses(ReflectionProvider $reflection, string $one, string $other): bool
    {
        if (strtolower($one) === strtolower($other) || !$reflection->hasClass($one) || !$reflection->hasClass($other)) {
            return false;
        }

        $first = $reflection->getClass($one);
        $second = $reflection->getClass($other);

        if ($first->isInterface() || $first->isTrait() || $second->isInterface() || $second->isTrait()) {
            return false;
        }

        return !$first->isSubclassOfClass($second) && !$second->isSubclassOfClass($first);
    }

    /**
     * @param non-empty-string $identifier
     */
    private static function error(string $problem, string $identifier, int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message('This understudy specification cannot work: ' . $problem)
            ->identifier($identifier)
            ->line($line)
            ->build();
    }
}
