<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Internal;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

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

    private function __construct() {}

    /**
     * @param string $verb the verb in lower case, without its namespace
     *
     * @return list<IdentifierRuleError>
     */
    public static function errors(FuncCall|StaticCall $call, string $verb, Scope $scope): array
    {
        $arguments = array_values($call->getArgs());

        // Every closure argument, not the first one: `verifySequence()` takes
        // a whole protocol, and a mistake in its third step is the same
        // mistake as in its first. The non-closure arguments of `verify()`
        // are the cardinality, checked below.
        $closures = array_values(array_filter(
            array_map(static fn(Arg $argument): Expr => $argument->value, $arguments),
            static fn(Expr $value): bool => $value instanceof Closure || $value instanceof ArrowFunction,
        ));

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
            $problem = Cardinality::verifyProblem(self::namedLiterals($arguments));

            if ($problem !== null) {
                $errors[] = self::error($problem, self::CARDINALITY_IDENTIFIER, $call->getStartLine());
            }
        }

        foreach ($closures as $closure) {
            foreach (self::matcherErrors($closure, $scope) as $error) {
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
    private static function matcherErrors(Expr $closure, Scope $scope): array
    {
        $specified = SpecifiedCall::of($closure, $scope);

        if (!$specified instanceof \Rasuvaeff\Understudy\PhpStan\Internal\SpecifiedCall) {
            return [];
        }

        $parameters = $specified->parameters();
        $errors = [];

        foreach (array_values($specified->call->getArgs()) as $position => $argument) {
            $matcher = MatcherName::of($argument->value);
            $parameter = $parameters[$position] ?? null;

            if ($matcher === null || $parameter === null) {
                continue;
            }

            $problem = MatcherKind::problem($matcher, $parameter->getType());

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
     * @param list<Arg> $arguments
     *
     * @return array<string, int|bool|null>
     */
    private static function namedLiterals(array $arguments): array
    {
        $literals = [];

        foreach ($arguments as $argument) {
            if (!$argument->name instanceof Identifier) {
                continue;
            }

            $value = $argument->value;
            $literals[$argument->name->toString()] = match (true) {
                $value instanceof Int_ => $value->value,
                $value instanceof ConstFetch => match (strtolower($value->name->toString())) {
                    'true' => true,
                    'false' => false,
                    default => null,
                },
                default => null,
            };
        }

        return $literals;
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
