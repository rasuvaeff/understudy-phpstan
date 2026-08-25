<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Tests\Internal;

use Rasuvaeff\Understudy\PhpStan\Internal\SpecificationExpr;
use Rasuvaeff\Understudy\PhpStan\Tests\Support\Parse;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * Which expressions are specifications, in either spelling.
 *
 * Both answer here so that no rule has to know there are two — and the
 * static form is the one Pest users are told to reach for, because Pest owns
 * the global `expect()`. A rule that only knew the free functions would be
 * silent exactly for them.
 *
 * @internal
 */
#[Test]
#[Covers(SpecificationExpr::class)]
final class SpecificationExprTest
{
    #[DataProvider('expressionProvider')]
    public function readsTheVerbOfASpecification(string $code, ?string $expected): void
    {
        Assert::same(SpecificationExpr::verbOf(Parse::expression($code)), $expected);
    }

    public static function expressionProvider(): iterable
    {
        yield 'an imported function' => [
            "use function Rasuvaeff\\Understudy\\when;\nwhen(fn () => \$double->find(1));",
            'when',
        ];
        yield 'a fully qualified function' => ['\Rasuvaeff\Understudy\verify(fn () => $double->find(1));', 'verify'];
        yield 'the static form' => [
            "use Rasuvaeff\\Understudy\\Understudy;\nUnderstudy::expect(fn () => \$double->find(1));",
            'expect',
        ];
        yield 'a reader with no function spelling' => [
            '\Rasuvaeff\Understudy\Understudy::verifySequence(fn () => $double->find(1));',
            'verifysequence',
        ];
        yield 'lastCall, which arrived after the Psalm plugin was written' => [
            '\Rasuvaeff\Understudy\Understudy::lastCall(fn () => $double->find(1));',
            'lastcall',
        ];

        yield 'a verb on somebody else' => ['\App\Testing\Doubles::when(fn () => 1);', null];
        // An unqualified name is claimed, and that is the deliberate trade:
        // `use function Rasuvaeff\Understudy\expect;` leaves nothing in the
        // call itself to resolve, and refusing unqualified names would make
        // the extension silent for the spelling test files actually use.
        // Pest's global `expect($value)` is claimed with it and costs
        // nothing: no rule of ours fires without a closure argument, and the
        // return-type extensions go by the function PHPStan resolved, not by
        // the word.
        yield 'somebody else\'s global expect is claimed too' => ["namespace App;\nexpect(1);", 'expect'];
        yield 'our class, not a verb' => ['\Rasuvaeff\Understudy\Understudy::for(\App\Gate::class);', null];
        yield 'not a call at all' => ['$specification;', null];
        yield 'a method call' => ['$builder->times(1);', null];
    }
}
