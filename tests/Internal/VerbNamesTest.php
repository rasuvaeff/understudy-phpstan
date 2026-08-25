<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Tests\Internal;

use Rasuvaeff\Understudy\PhpStan\Internal\VerbNames;
use Rasuvaeff\Understudy\Understudy;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * Which names the extension claims. Getting this wrong in either direction is
 * bad in a different way: too narrow and the extension does nothing for the form
 * a user actually wrote, too wide and it claims somebody else's function
 * that happens to be called `expect`.
 *
 * @internal
 */
#[Test]
#[Covers(VerbNames::class)]
final class VerbNamesTest
{
    #[DataProvider('functionProvider')]
    public function decidesWhetherAFunctionNameIsOurs(string $name, bool $expected): void
    {
        Assert::same(VerbNames::isFunction($name), $expected);
    }

    public static function functionProvider(): iterable
    {
        yield 'imported when' => ['when', true];
        yield 'imported expect' => ['expect', true];
        yield 'imported verify' => ['verify', true];
        yield 'case is not meaningful in PHP' => ['When', true];
        yield 'fully qualified' => ['Rasuvaeff\Understudy\when', true];
        yield 'fully qualified, leading separator' => ['rasuvaeff\understudy\expect', true];
        // Pest owns a global expect(). It is a different function that happens
        // to share a word, and silencing it would be somebody else's bug.
        yield 'another vendor expect' => ['Pest\expect', false];
        yield 'another vendor when' => ['App\Support\when', false];
        yield 'our namespace, not a verb' => ['Rasuvaeff\Understudy\forwarding', false];
        yield 'not a verb at all' => ['array_map', false];
        // Another vendor's namespace is never ours, whatever its length: the
        // prefix decides, not what happens to sit at the same offset.
        yield 'a foreign namespace as long as ours' => ['app\\aaaaaaaaaaaaaaaa\\when', false];
    }

    #[DataProvider('staticProvider')]
    public function decidesWhetherAStaticCallIsOurs(string $class, string $method, bool $expected): void
    {
        Assert::same(VerbNames::isStaticCall($class, $method), $expected);
    }

    public static function staticProvider(): iterable
    {
        yield 'the static form' => [Understudy::class, 'when', true];
        // Built rather than written out: a literal FQCN string is what
        // rector rewrites into `::class`, and `::class` has no leading
        // separator — which is the whole point of this case.
        yield 'leading separator' => ['\\' . Understudy::class, 'expect', true];
        yield 'lowercased' => ['rasuvaeff\understudy\understudy', 'verify', true];
        yield 'our class, not a verb' => [Understudy::class, 'for', false];
        yield 'a verb on somebody else' => ['App\Testing\Doubles', 'when', false];
        yield 'a namesake class elsewhere' => ['App\\Understudy', 'when', false];
        yield 'an upper-case method' => [\Rasuvaeff\Understudy\Understudy::class, 'When', true];
    }

    #[DataProvider('shortVerbProvider')]
    public function readsTheVerbWithoutItsNamespace(string $name, string $expected): void
    {
        Assert::same(VerbNames::shortVerb($name), $expected);
    }

    public static function shortVerbProvider(): iterable
    {
        yield 'imported' => ['when', 'when'];
        yield 'fully qualified' => ['Rasuvaeff\Understudy\verify', 'verify'];
        yield 'case is not meaningful in PHP' => ['Rasuvaeff\Understudy\Expect', 'expect'];
    }
}
