<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Tests\Integration;

use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

/**
 * The whole extension, driven as a real PHPStan process over fixture
 * projects.
 *
 * A unit test can prove that a rule returns an error for a node it is handed.
 * It cannot prove that PHPStan hands it that node, that the neon file
 * registers it, that a collector's data arrives keyed the way the rule reads
 * it, or that a matcher stops being a type error — those live in the seam
 * with the analyser, and the only way to observe a seam is to run it.
 *
 * The control run — same files, same level, no extension — is what tells a
 * working extension apart from one that loads and does nothing.
 *
 * @internal
 */
#[Test]
#[CoversNothing]
final class ExtensionIntegrationTest
{
    public function withoutTheExtensionEveryMatcherIsAnError(): void
    {
        // The premise. If PHPStan has nothing to say about matchers at this
        // level even without the extension, the test below proves nothing.
        // Thirteen: five argument reports on the original lines, two on the
        // `Arg::rest()` prefix specification (the arity and the matcher's own
        // argument), one on the captor's `capture()`, five on the matchers of
        // the two armed protocols.
        $report = $this->analyse('Matchers', 'phpstan-without-extension.neon');

        Assert::same($this->countIn($report, 'Correct.php'), 13);
    }

    public function withTheExtensionAMatcherFitsWhateverTheContractDeclares(): void
    {
        $report = $this->analyse('Matchers');

        Assert::same($this->countIn($report, 'Correct.php'), 0);
    }

    /**
     * A verb the extension does not know is worse than no extension at all:
     * every matcher inside it becomes a leak report on correct code, because
     * `ArgReturnType` has already typed the matcher `never` and nothing
     * records the call as a specification.
     *
     * `expectSequence()` was outside every rule until 0.2.2 — the second verb
     * to be, after `lastCall()`. Both of its spellings are exercised in the
     * fixture, and this is the assertion that would have caught it: five
     * false leaks in `Correct.php`, and the one true report in `Wrong.php`
     * replaced by a leak that names the wrong mistake.
     */
    public function aProtocolStepIsASpecificationLikeAnyOther(): void
    {
        $identifiers = $this->identifiersIn($this->analyse('Matchers'), 'Wrong.php');

        // Two wrong-kind matchers: one in a plain `when()`, one in the second
        // step of an armed protocol.
        Assert::same(
            count(array_filter(
                $identifiers,
                static fn(string $identifier): bool => $identifier === 'understudy.matcher',
            )),
            2,
        );
    }

    public function nothingAroundTheMatcherIsSilenced(): void
    {
        $report = $this->analyse('Matchers');
        $messages = $this->messagesIn($report, 'Wrong.php');

        // A wrong argument beside a matcher, and a method the contract does
        // not have: both are PHPStan's own reports, and both survive typing
        // the matcher as `never`. This is the difference between a type that
        // fits and a suppressed diagnostic.
        Assert::same(
            count(array_filter(
                $messages,
                static fn(array $m): bool => str_contains($m['message'], 'expects int, string given'),
            )),
            1,
        );
        Assert::same(
            count(array_filter(
                $messages,
                static fn(array $m): bool => str_contains($m['message'], 'undefined method'),
            )),
            1,
        );
    }

    public function aMatcherOfTheWrongKindAndOneOutsideASpecificationAreReported(): void
    {
        $identifiers = $this->identifiersIn($this->analyse('Matchers'), 'Wrong.php');

        Assert::true(\in_array('understudy.matcher', $identifiers, strict: true));
        Assert::true(\in_array('understudy.matcherLeak', $identifiers, strict: true));

        // Five leaks: the original three plus an `Arg::int()` and a
        // `capture()` immediately before a specification on the same line.
        // Line ranges cannot distinguish those calls; file offsets can. The
        // arity report is quiet by design — the leak names the actual
        // mistake — which is what the absent identifier pins.
        Assert::same(
            count(array_filter(
                $identifiers,
                static fn(string $identifier): bool => $identifier === 'understudy.matcherLeak',
            )),
            5,
        );
        Assert::false(\in_array('arguments.count', $identifiers, strict: true));
    }

    public function everyMisuseIsReportedAndNothingElseIs(): void
    {
        $report = $this->analyse('Misuse');

        // Fifteen mistakes, fifteen reports, all of them ours. The last
        // five are impossible bounds written past the first link of the
        // chain, which the rule used to walk straight past.
        Assert::same($this->countIn($report, 'Wrong.php'), 15);
        Assert::same(
            array_values(array_unique(array_map(
                static fn(string $identifier): string => explode('.', $identifier)[0],
                $this->identifiersIn($report, 'Wrong.php'),
            ))),
            ['understudy'],
        );

        // And the control group: each of these is the nearest correct
        // neighbour of a mistake next door. A false accusation here is worse
        // than a missed one, because a user cannot act on it.
        Assert::same($this->countIn($report, 'Right.php'), 0);
    }

    /**
     * The rules answer a question about the specification, not about types,
     * so they have to answer it at the level PHPStan users actually run.
     * Level 9 is where the matcher typing matters; level 0 is where most
     * projects live, and a rule that only fired at 9 would be missing for
     * them.
     */
    public function theMisuseRulesFireAtTheLowestLevelToo(): void
    {
        $report = $this->analyse('Misuse', 'phpstan-level-0.neon');

        Assert::same($this->countIn($report, 'Wrong.php'), 15);
        Assert::same($this->countIn($report, 'Right.php'), 0);
    }

    public function anAnswerOfTheWrongShapeIsReported(): void
    {
        $report = $this->analyse('Returns');

        // Five of the eight are not our own rules at all: filling in the
        // builder's template parameter is all the extension does there, and
        // PHPStan checks `returns()` and `answers()` against it on its own.
        // The other three are the one the parameter cannot carry — a void
        // method, whose `WhenBuilder<void>` nobody could satisfy — written
        // directly on the verb and past a `times()` and a `then()` link,
        // which the rule used to walk straight past.
        Assert::same($this->countIn($report, 'Wrong.php'), 8);
        Assert::same(
            array_count_values($this->identifiersIn($report, 'Wrong.php')),
            ['argument.type' => 5, 'understudy.returns' => 3],
        );

        // Answers that fit, and the shapes the extension declines to judge.
        Assert::same($this->countIn($report, 'Right.php'), 0);
    }

    public function theWireShapeIsReadFromTheConstructor(): void
    {
        $report = $this->analyse('Wire');
        $identifiers = $this->identifiersIn($report, 'Wrong.php');

        // A key the constructor has no parameter for, and a method the
        // contract behind a key does not have.
        Assert::true(\in_array('offsetAccess.notFound', $identifiers, strict: true));
        Assert::true(\in_array('method.notFound', $identifiers, strict: true));
        Assert::same($this->countIn($report, 'Right.php'), 0);
    }

    /**
     * The shape has to agree with what `Wire::resolve()` actually does with a
     * parameter naming more than one contract, and it did not.
     *
     * An INTERSECTION is one double standing for every contract in it — the
     * core sends it down the same branch a single contract takes. The
     * extension dropped the parameter instead, so its key was reported as a
     * missing offset on code that wires and runs.
     *
     * A UNION of two object types is refused outright: `CannotWire` aborts
     * the call, so no key exists and no shape describes it. Reporting a
     * missing key there named the wrong mistake; the core's own declaration
     * stands and the double is a plain `object`.
     */
    public function theWireShapeAgreesWithTheCoreOnUnionsAndIntersections(): void
    {
        $report = $this->analyse('Wire');

        // The intersection lives in Right.php and both of its halves are
        // called there, which the count above pins at zero reports.
        Assert::same($this->countIn($report, 'Right.php'), 0);

        $messages = $this->messagesIn($report, 'Wrong.php');

        Assert::same(
            count(array_filter(
                $messages,
                static fn(array $m): bool => str_contains($m['message'], 'undefined method object::now()'),
            )),
            1,
        );

        // And no report claiming the refused union's key is missing: the key
        // is not missing, the call never returns.
        Assert::same(
            count(array_filter(
                $messages,
                static fn(array $m): bool => str_contains($m['message'], "Offset 'either'"),
            )),
            0,
        );
    }

    /**
     * @return array<string, list<array{message: string, line: int|null, identifier?: string|null}>>
     */
    private function analyse(string $fixture, string $config = 'phpstan.neon'): array
    {
        $root = \dirname(__DIR__, 2);
        $project = __DIR__ . '/Fixtures/' . $fixture;

        $command = sprintf(
            '%s %s analyse --configuration=%s --error-format=json --no-progress --autoload-file=%s 2>/dev/null',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($root . '/vendor/bin/phpstan'),
            escapeshellarg($project . '/' . $config),
            escapeshellarg($root . '/vendor/autoload.php'),
        );

        exec($command, $lines);

        $decoded = json_decode(implode("\n", $lines), associative: true);

        if (!\is_array($decoded) || !isset($decoded['files']) || !\is_array($decoded['files'])) {
            return [];
        }

        $report = [];

        /** @var array<string, array{messages?: list<array{message: string, line: int|null, identifier?: string|null}>}> $files */
        $files = $decoded['files'];

        foreach ($files as $file => $data) {
            $report[$file] = array_values($data['messages'] ?? []);
        }

        return $report;
    }

    /**
     * @param array<string, list<array{message: string, line: int|null, identifier?: string|null}>> $report
     *
     * @return list<array{message: string, line: int|null, identifier?: string|null}>
     */
    private function messagesIn(array $report, string $file): array
    {
        foreach ($report as $path => $messages) {
            if (str_ends_with($path, $file)) {
                return $messages;
            }
        }

        return [];
    }

    /**
     * @param array<string, list<array{message: string, line: int|null, identifier?: string|null}>> $report
     */
    private function countIn(array $report, string $file): int
    {
        return \count($this->messagesIn($report, $file));
    }

    /**
     * @param array<string, list<array{message: string, line: int|null, identifier?: string|null}>> $report
     *
     * @return list<string>
     */
    private function identifiersIn(array $report, string $file): array
    {
        return array_values(array_map(
            static fn(array $message): string => $message['identifier'] ?? '',
            $this->messagesIn($report, $file),
        ));
    }
}
