<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Tests\Internal;

use Rasuvaeff\Understudy\PhpStan\Internal\MatcherName;
use Rasuvaeff\Understudy\PhpStan\Tests\Support\Parse;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * Which static calls are matchers of ours.
 *
 * Matched on the resolved class, not on the short name: claiming
 * `MyArg::int()` would make the extension type somebody else's method as
 * `never`, which is the one mistake in here a user could not work around.
 *
 * @internal
 */
#[Test]
#[Covers(MatcherName::class)]
final class MatcherNameTest
{
    #[DataProvider('matcherProvider')]
    public function readsTheMatcherName(string $code, ?string $expected): void
    {
        Assert::same(MatcherName::of(Parse::expression($code)), $expected);
    }

    public static function matcherProvider(): iterable
    {
        yield 'imported' => [
            "use Rasuvaeff\\Understudy\\Arg;\nArg::int();",
            'int',
        ];
        yield 'fully qualified' => ['\Rasuvaeff\Understudy\Arg::any();', 'any'];
        yield 'aliased on import' => [
            "use Rasuvaeff\\Understudy\\Arg as Matcher;\nMatcher::string();",
            'string',
        ];
        yield 'case is not meaningful in PHP' => ['\Rasuvaeff\Understudy\ARG::same(1);', 'same'];

        yield 'a namesake class elsewhere' => ["namespace App;\nArg::int();", null];
        yield 'a class whose name ends in Arg' => ['\App\MyArg::int();', null];
        yield 'a method call, not a static one' => ['$arg->int();', null];
        yield 'not a call at all' => ['$argument;', null];
    }
}
