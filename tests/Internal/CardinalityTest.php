<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Tests\Internal;

use Rasuvaeff\Understudy\PhpStan\Internal\Cardinality;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * Claims about how often a call happens that no run could satisfy.
 *
 * Read off literals only: `times($min, $max)` with variables is a question
 * about values, not about the specification, and an extension guessing there
 * would be wrong exactly when it mattered.
 *
 * @internal
 */
#[Test]
#[Covers(Cardinality::class)]
final class CardinalityTest
{
    #[DataProvider('timesProvider')]
    public function judgesTheFluentBounds(?int $minimum, ?int $maximum, bool $expectProblem): void
    {
        Assert::same(Cardinality::timesProblem($minimum, $maximum) !== null, $expectProblem);
    }

    public static function timesProvider(): iterable
    {
        yield 'a range that can be met' => [2, 5, false];
        yield 'an exact count' => [3, null, false];
        yield 'never, as a range' => [0, 0, false];
        yield 'nothing said at all' => [null, null, false];
        yield 'unknown minimum, known maximum' => [null, 4, false];
        yield 'swapped bounds' => [5, 2, true];
        yield 'negative minimum' => [-1, null, true];
        yield 'negative maximum' => [null, -1, true];
    }

    #[DataProvider('verifyProvider')]
    public function judgesTheNamedArguments(array $arguments, bool $expectProblem): void
    {
        Assert::same(Cardinality::verifyProblem($arguments) !== null, $expectProblem);
    }

    public static function verifyProvider(): iterable
    {
        yield 'an exact count' => [['times' => 1], false];
        yield 'a range' => [['minimum' => 1, 'maximum' => 3], false];
        yield 'never on its own' => [['never' => true], false];
        yield 'never: false beside a count is not a contradiction' => [['never' => false, 'times' => 2], false];
        yield 'nothing said at all' => [[], false];
        // A variable argument reads as null, and null is not a claim.
        yield 'a count the extension cannot read' => [['times' => null], false];

        yield 'never and an exact count' => [['never' => true, 'times' => 3], true];
        yield 'never and a minimum' => [['never' => true, 'minimum' => 1], true];
        yield 'never and a maximum' => [['never' => true, 'maximum' => 2], true];
        yield 'an exact count and a minimum' => [['times' => 3, 'minimum' => 1], true];
        yield 'an exact count and a maximum' => [['times' => 3, 'maximum' => 5], true];
        yield 'swapped bounds' => [['minimum' => 5, 'maximum' => 2], true];
    }

    public function namesTheArgumentThatContradictsNever(): void
    {
        $problem = Cardinality::verifyProblem(['never' => true, 'maximum' => 2]);

        Assert::string($problem ?? '')->contains('maximum');
    }
}
