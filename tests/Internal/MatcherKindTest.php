<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Tests\Internal;

use PHPStan\Type\Accessory\AccessoryNonEmptyStringType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\CallableType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\IterableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Rasuvaeff\Understudy\PhpStan\Internal\MatcherKind;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * Whether a matcher can ever match a parameter of a given type.
 *
 * The rule this class exists to keep: report a definite "no", stay silent on
 * "maybe". A refined type — `non-empty-string`, an int range — answers
 * "maybe" to its plain kind, and a matcher can produce a value that fits it.
 * Comparing type names, which is what the Psalm plugin has to do, would call
 * those mistakes.
 *
 * @internal
 */
#[Test]
#[Covers(MatcherKind::class)]
final class MatcherKindTest
{
    #[DataProvider('pairingProvider')]
    public function judgesTheKindAgainstTheParameter(string $matcher, Type $parameter, bool $expectProblem): void
    {
        Assert::same(MatcherKind::problem($matcher, $parameter) !== null, $expectProblem);
    }

    public static function pairingProvider(): iterable
    {
        yield 'int into int' => ['int', new IntegerType(), false];
        yield 'string into string' => ['string', new StringType(), false];
        yield 'bool into bool' => ['bool', new BooleanType(), false];
        yield 'float into float' => ['float', new FloatType(), false];

        // PHP widens an int wherever a float is declared, and the engine
        // follows that.
        yield 'int into float' => ['int', new FloatType(), false];

        // Refinements the matcher can still satisfy.
        yield 'string into non-empty-string' => [
            'string',
            new IntersectionType([new StringType(), new AccessoryNonEmptyStringType()]),
            false,
        ];
        yield 'string into a literal' => ['string', new ConstantStringType('ord-1'), false];
        yield 'int into a range' => ['int', IntegerRangeType::fromInterval(1, 10), false];
        yield 'int into a union that has one' => [
            'int',
            TypeCombinator::union(new IntegerType(), new StringType()),
            false,
        ];
        yield 'int into mixed' => ['int', new MixedType(), false];
        yield 'string into a nullable string' => [
            'string',
            TypeCombinator::addNull(new StringType()),
            false,
        ];

        // Kinds the matcher name cannot decide: never a complaint.
        yield 'any into int' => ['any', new IntegerType(), false];
        yield 'same into int' => ['same', new IntegerType(), false];
        yield 'instanceOf into an object' => ['instanceOf', new ObjectType(\ArrayObject::class), false];

        // `containing()` asks `is_array()`; `count()` takes an array or a
        // Countable, and every object is a maybe; `which()` needs an object.
        // The same table the Psalm plugin reads.
        yield 'containing into an array' => ['containing', new ArrayType(new IntegerType(), new StringType()), false];
        yield 'containing into an iterable' => ['containing', new IterableType(new MixedType(), new MixedType()), false];
        yield 'containing into a callable' => ['containing', new CallableType(), false];
        yield 'containing into mixed' => ['containing', new MixedType(), false];
        yield 'count into an array' => ['count', new ArrayType(new IntegerType(), new StringType()), false];
        yield 'count into an object' => ['count', new ObjectType(\ArrayObject::class), false];
        yield 'count into a bare object' => ['count', new ObjectWithoutClassType(), false];
        yield 'count into a callable' => ['count', new CallableType(), false];
        yield 'which into an object' => ['which', new ObjectType(\ArrayObject::class), false];
        yield 'which into a callable' => ['which', new CallableType(), false];
        yield 'which into an iterable' => ['which', new IterableType(new MixedType(), new MixedType()), false];
        yield 'which into a nullable object' => ['which', TypeCombinator::addNull(new ObjectWithoutClassType()), false];

        // The pairings no argument can satisfy.
        yield 'int into string' => ['int', new StringType(), true];
        yield 'string into int' => ['string', new IntegerType(), true];
        yield 'bool into string' => ['bool', new StringType(), true];
        yield 'float into string' => ['float', new StringType(), true];
        yield 'string into an array' => ['string', new ArrayType(new IntegerType(), new StringType()), true];
        yield 'float into int' => ['float', new IntegerType(), true];
        yield 'containing into int' => ['containing', new IntegerType(), true];
        yield 'containing into a bare object' => ['containing', new ObjectWithoutClassType(), true];
        yield 'count into string' => ['count', new StringType(), true];
        yield 'count into a nullable int' => ['count', TypeCombinator::addNull(new IntegerType()), true];
        yield 'which into string' => ['which', new StringType(), true];
        yield 'which into an array' => ['which', new ArrayType(new IntegerType(), new StringType()), true];
        yield 'the shape name is case-insensitive' => ['Count', new StringType(), true];
    }

    #[DataProvider('instanceProvider')]
    public function holdsAnInstanceOnlyWhereOneCanBe(Type $parameter, bool $unrelated, bool $reported): void
    {
        $asked = [];
        $problem = MatcherKind::instanceProblem(
            '`Arg::instanceOf()`',
            'Fixture\\Pen',
            $parameter,
            static function (string $one, string $other) use ($unrelated, &$asked): bool {
                $asked[] = $one;

                return $unrelated;
            },
        );

        Assert::same($problem !== null, $reported);

        foreach ($asked as $class) {
            Assert::same($class, 'Fixture\\Pen');
        }
    }

    /**
     * Reported pairings are object-free: describing an object type needs a
     * running analysis (see below), so an unrelated class is reported in
     * the `Kinds` fixture instead.
     */
    public static function instanceProvider(): iterable
    {
        yield 'into int' => [new IntegerType(), false, true];
        yield 'into a nullable string' => [TypeCombinator::addNull(new StringType()), false, true];
        yield 'into an array' => [new ArrayType(new IntegerType(), new StringType()), false, true];
        yield 'into a related class' => [new ObjectType(\ArrayObject::class), false, false];
        yield 'into a bare object' => [new ObjectWithoutClassType(), true, false];
        yield 'into a callable' => [new CallableType(), true, false];
        yield 'into an iterable' => [new IterableType(new MixedType(), new MixedType()), true, false];
        yield 'into mixed' => [new MixedType(), true, false];
        yield 'into an intersection' => [
            new IntersectionType([new ObjectType(\ArrayObject::class), new ObjectType(\Countable::class)]),
            true,
            false,
        ];
    }

    public function theInstanceComplaintNamesTheClass(): void
    {
        Assert::same(
            MatcherKind::instanceProblem(
                '`capture()`',
                'Fixture\\Pen',
                TypeCombinator::addNull(new IntegerType()),
                static fn(string $one, string $other): bool => true,
            ),
            '`capture()` matches a value of type Fixture\\Pen, and this parameter accepts int|null. '
            . 'No argument can satisfy both, so the specification can never match.',
        );
    }

    public function theShapeIsNamedByWhatItMatches(): void
    {
        Assert::same(
            MatcherKind::problem('count', new StringType()),
            '`Arg::count()` matches a value of type array|Countable, and this parameter accepts string. '
            . 'No argument can satisfy both, so the specification can never match.',
        );
    }

    /**
     * Asserted whole rather than by fragments: a message is what a user acts
     * on, and every half of a concatenation in one is a mutant a
     * `contains()` cannot see.
     */
    public function theComplaintNamesBothSides(): void
    {
        Assert::same(
            MatcherKind::problem('int', new StringType()),
            '`Arg::int()` matches a value of type int, and this parameter accepts string. '
            . 'No argument can satisfy both, so the specification can never match.',
        );
    }

    /**
     * An object parameter is deliberately absent from the provider above.
     * Describing one asks PHPStan for its reflection provider, which exists
     * only inside a running analysis — so the pairing is covered where it can
     * be observed honestly, by the `Misuse` fixture in
     * `tests/Integration/Fixtures`, and asserting it here would only prove
     * that a test can build a type.
     */
    public function theMatcherNameIsReadWithoutRegardForCase(): void
    {
        Assert::true(MatcherKind::problem('Int', new StringType()) !== null);
    }
}
