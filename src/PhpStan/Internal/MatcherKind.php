<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Internal;

use PHPStan\Type\BooleanType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
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
     * `same`, `not`, `containing`, `count`, `which`, `instanceOf`,
     * `satisfies`, `any`, `none` and `remaining` have no entry on purpose:
     * their argument decides what they match, so the kind is not knowable
     * from the matcher name alone.
     *
     * @param non-empty-string $matcher the `Arg::` method name
     */
    public static function problem(string $matcher, Type $parameterType): ?string
    {
        $kinds = self::candidates($matcher);

        if ($kinds === []) {
            return null;
        }

        foreach ($kinds as $kind) {
            if (!$parameterType->isSuperTypeOf($kind)->no()) {
                return null;
            }
        }

        return sprintf(
            '`Arg::%s()` matches a value of type %s, and this parameter accepts %s. '
            . 'No argument can satisfy both, so the specification can never match.',
            $matcher,
            $kinds[0]->describe(VerbosityLevel::typeOnly()),
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
