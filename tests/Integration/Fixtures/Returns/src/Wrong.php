<?php

declare(strict_types=1);

namespace Fixture\Returns;

use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

/**
 * `open(int $code): bool`. Every answer here is the wrong shape for it, and
 * none of these lines is checkable while `when()` is declared to produce a
 * `WhenBuilder<mixed>`.
 */
final class Wrong
{
    public function aStringWhereABoolIsDeclared(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static fn(): bool => $gate->open(1))->returns('yes');
    }

    public function aWrongAnswerCallback(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static fn(): bool => $gate->open(1))->answers(static fn(): string => 'yes');
    }

    public function aWrongValueAfterAReturnClosure(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static function () use (&$gate): bool {
            return $gate->open(1);
        })->returns('yes');
    }

    public function aWrongValueInASequence(): void
    {
        $gate = Understudy::for(Gate::class);

        // The first is fine; the second is not, and a sequence has to be
        // checked element by element.
        when(static fn(): bool => $gate->open(1))->returns(true, 'no');
    }

    /**
     * A void method has no value to hand back, and the builder's template
     * parameter cannot say so: `WhenBuilder<void>` is not a claim anybody
     * can satisfy. `Rule\VoidReturnsRule` says it instead.
     */
    public function returnsOnAVoidMethod(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static function () use ($gate): void {
            $gate->close();
        })->returns(null);
    }

    public function theStaticFormIsCheckedToo(): void
    {
        $gate = Understudy::for(Gate::class);

        Understudy::when(static fn(): bool => $gate->open(1))->returns(7);
    }

    /**
     * The same claim past the first link of the chain. Reading only the
     * immediate receiver of `returns()` found a `MethodCall` there and gave
     * up, so both of these were silent while the form above was reported.
     */
    public function returnsOnAVoidMethodAfterTimes(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static function () use ($gate): void {
            $gate->close();
        })->times(2)->returns(null);
    }

    public function returnsOnAVoidMethodAfterThen(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static function () use ($gate): void {
            $gate->close();
        })->throws(new \RuntimeException('closed'))->then()->returns(null);
    }
}
