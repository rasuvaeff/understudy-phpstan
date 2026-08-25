<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Tests\Internal;

use PHPStan\Type\Accessory\AccessoryNonEmptyStringType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
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

        // The pairings no argument can satisfy.
        yield 'int into string' => ['int', new StringType(), true];
        yield 'string into int' => ['string', new IntegerType(), true];
        yield 'bool into string' => ['bool', new StringType(), true];
        yield 'float into string' => ['float', new StringType(), true];
        yield 'string into an array' => ['string', new ArrayType(new IntegerType(), new StringType()), true];
        yield 'float into int' => ['float', new IntegerType(), true];
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
