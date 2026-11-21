<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Internal;

use PHPStan\Type\BooleanType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;

/**
 * Whether a matcher can ever match a parameter of a given type.
 *
 * Deliberately about the KIND, not about assignability. `Arg::int()` cannot
 * match a `string` parameter under any argument, and that is worth saying
 * before the test runs; `Arg::any()` can match anything and never complains.
 *
 * The question is asked of PHPStan's type algebra rather than of type names,
 * and only a definite "no" is reported. A `non-empty-string` parameter
 * answers "maybe" to a plain `string`, and rightly so — `Arg::string()` can
 * match a non-empty one. Comparing names would have called that a mistake.
 *
 * @internal
 */
final class MatcherKind
{
    private function __construct() {}

    /**
     * The complaint, or null when this pairing is fine or unknowable.
     *
     * `same`, `not`, `satisfies`, `any`, `none` and `remaining` have no entry
     * on purpose: their argument decides what they match, so the kind is not
     * knowable from the matcher name alone. `instanceOf` and a captor's
     * `capture()` name their class in an argument, and are judged by
     * {@see instanceProblem()} instead.
     *
     * @param non-empty-string $matcher the `Arg::` method name
     */
    public static function problem(string $matcher, Type $parameterType): ?string
    {
        $lower = strtolower($matcher);
        $shape = self::SHAPES[$lower] ?? null;

        if ($shape !== null) {
            foreach (self::members($parameterType) as $member) {
                if (!self::rejectsShape($lower, $member)) {
                    return null;
                }
            }

            return self::complaint('`Arg::' . $matcher . '()`', $shape, $parameterType);
        }

        $kinds = self::candidates($matcher);

        if ($kinds === []) {
            return null;
        }

        foreach ($kinds as $kind) {
            if (!$parameterType->isSuperTypeOf($kind)->no()) {
                return null;
            }
        }

        return self::complaint(
            '`Arg::' . $matcher . '()`',
            $kinds[0]->describe(VerbosityLevel::typeOnly()),
            $parameterType,
        );
    }

    /**
     * `Arg::instanceOf(Foo::class)`, or a `capture()` on a `Captor<Foo>`:
     * a matcher that holds only instances of one class.
     *
     * A parameter that can hold no object at all is a definite no. So is a
     * parameter of another CLASS, when neither of the two extends the other:
     * no object is an instance of two unrelated classes, and that stays true
     * of a double too — `Understudy::for()` can combine a class with
     * interfaces, never with a second class.
     *
     * An interface on either side is a maybe, final or not — which is why
     * this does not ask `isSuperTypeOf()`. PHPStan would rightly call a final
     * class that does not implement an interface a definite no, and
     * `Understudy::for(Final::class, Contract::class)` builds exactly that
     * object.
     *
     * @param non-empty-string               $matcher   the matcher as written, for the message
     * @param non-empty-string               $class     the class the matcher holds instances of
     * @param \Closure(string, string): bool $unrelated whether two names are classes, neither
     *                                                  extending the other
     */
    public static function instanceProblem(
        string $matcher,
        string $class,
        Type $parameterType,
        \Closure $unrelated,
    ): ?string {
        foreach (self::members($parameterType) as $member) {
            if (!self::rejectsInstance($class, $member, $unrelated)) {
                return null;
            }
        }

        return self::complaint($matcher, $class, $parameterType);
    }

    /**
     * Matchers whose name alone says what shape they match, and the name of
     * that shape for the message.
     *
     * `containing` asks `is_array()`. `count` takes an array or a
     * `Countable`, and every object is a maybe: whether a class implements
     * `Countable` is not asked, the same line the Psalm plugin draws.
     * `which` reads a getter and `whichProperty` a public property, so both
     * need an object.
     */
    private const array SHAPES = [
        'containing' => 'array',
        'count' => 'array|Countable',
        'which' => 'object',
        'whichproperty' => 'object',
    ];

    private static function rejectsShape(string $matcher, Type $member): bool
    {
        return match ($matcher) {
            'containing' => $member->isArray()->no(),
            'count' => $member->isArray()->no() && $member->isObject()->no(),
            default => $member->isObject()->no(),
        };
    }

    /**
     * @param \Closure(string, string): bool $unrelated
     */
    private static function rejectsInstance(string $class, Type $member, \Closure $unrelated): bool
    {
        if ($member->isObject()->no()) {
            return true;
        }

        return $member instanceof ObjectType && $unrelated($class, $member->getClassName());
    }

    /**
     * The members of a union, or the type itself. An intersection stays
     * whole: it may name the matcher's class among its parts, and it is a
     * maybe for that reason.
     *
     * @return list<Type>
     */
    private static function members(Type $type): array
    {
        return $type instanceof UnionType ? $type->getTypes() : [$type];
    }

    /**
     * @param non-empty-string $matcher
     */
    private static function complaint(string $matcher, string $kind, Type $parameterType): string
    {
        return sprintf(
            '%s matches a value of type %s, and this parameter accepts %s. '
            . 'No argument can satisfy both, so the specification can never match.',
            $matcher,
            $kind,
            $parameterType->describe(VerbosityLevel::typeOnly()),
        );
    }

    /**
     * Every type an argument of this matcher's kind could have.
     *
     * `int` carries `float` beside it because PHP widens an int wherever a
     * float is declared, and the engine follows that: an int argument
     * satisfies a float parameter.
     *
     * @return list<Type>
     */
    private static function candidates(string $matcher): array
    {
        return match (strtolower($matcher)) {
            'int' => [new IntegerType(), new FloatType()],
            'float' => [new FloatType()],
            'string' => [new StringType()],
            'bool' => [new BooleanType()],
            default => [],
        };
    }
}
