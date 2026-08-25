<?php

declare(strict_types=1);

namespace Fixture\Returns;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Invocation;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

/**
 * The control group: answers that fit, and the shapes the provider must
 * decline to judge rather than guess at.
 */
final class Right
{
    public function answersThatFit(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static fn(): bool => $gate->open(1))->returns(true);
        when(static fn(): bool => $gate->open(2))->returns(true, false);
        when(static fn(): bool => $gate->open(3))->answers(static fn(Invocation $call): bool => true);
        when(static fn(): bool => $gate->open(Arg::any()))->returns(false);
        Understudy::when(static fn(): bool => $gate->open(4))->returns(true);
    }

    public function aVoidMethodIsSpecifiedWithoutReturns(): void
    {
        $gate = Understudy::for(Gate::class);

        // Specifying a void method is normal. It is `returns()` beside one
        // that is not.
        when(static function () use ($gate): void {
            $gate->close();
        });
    }

    public function throwingIsNeverAboutTheReturnType(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static fn(): bool => $gate->open(1))->throws(new \RuntimeException('no'));
    }
}
