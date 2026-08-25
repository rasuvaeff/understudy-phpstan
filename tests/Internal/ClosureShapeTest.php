<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Tests\Internal;

use PhpParser\Node\Expr\FuncCall;
use Rasuvaeff\Understudy\PhpStan\Internal\ClosureShape;
use Rasuvaeff\Understudy\PhpStan\Tests\Support\Parse;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * How many calls a specification closure makes, and of what kind.
 *
 * The engine enforces "exactly one direct call" at runtime by throwing
 * `InvalidCallSpecification`; this is the same rule read off the syntax, so a
 * test that cannot possibly work says so before it runs.
 *
 * @internal
 */
#[Test]
#[Covers(ClosureShape::class)]
final class ClosureShapeTest
{
    #[DataProvider('shapeProvider')]
    public function readsTheShapeOfAClosure(string $code, ?string $expectedFragment): void
    {
        $call = Parse::expression($code);

        Assert::instanceOf($call, FuncCall::class);

        $problem = ClosureShape::of($call->getArgs()[0]->value)->problem();

        if ($expectedFragment === null) {
            Assert::null($problem);

            return;
        }

        Assert::string($problem ?? '')->contains($expectedFragment);
    }

    public static function shapeProvider(): iterable
    {
        yield 'one call, arrow function' => ['when(fn () => $double->find(1));', null];
        yield 'one call, closure' => ['when(function () use ($double) { $double->find(1); });', null];
        yield 'one nullsafe call' => ['when(fn () => $double?->find(1));', null];

        // A callback handed to something else is that thing's business, and
        // its calls are not this specification's.
        yield 'a nested closure is not descended into' => [
            'when(fn () => $double->each(fn () => $other->touch()));',
            null,
        ];

        yield 'no call at all' => ['when(fn () => true);', 'nothing to specify'];
        yield 'two calls' => ['when(fn () => $double->find(1) && $double->find(2));', 'makes 2 calls'];
        yield 'three calls' => [
            'when(function () use ($double) { $double->a(); $double->b(); $double->c(); });',
            'makes 3 calls',
        ];
        yield 'a static call a double cannot intercept' => [
            'when(fn () => Clock::now());',
            'static method',
        ];

        // Not a closure literal: a variable, a first-class callable, a
        // string. Nothing to read, and nothing to complain about either.
        yield 'a variable instead of a closure' => ['when($specification);', null];
        yield 'a first-class callable' => ['when($double->find(...));', null];
    }
}
