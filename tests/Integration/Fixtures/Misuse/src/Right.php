<?php

declare(strict_types=1);

namespace Fixture\Misuse;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\expect;
use function Rasuvaeff\Understudy\verify;
use function Rasuvaeff\Understudy\when;

/**
 * The control group for the rules: each of these is the nearest CORRECT
 * neighbour of a mistake next door. A plugin that reported any of them would
 * be worse than one that reported none, because a false accusation is one a
 * user cannot act on.
 */
final class Right
{
    public function matchersThatFit(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static fn(): bool => $gate->open(Arg::int(min: 1)));
        // `any` matches anything, and the kinds it cannot know stay silent.
        when(static fn(): bool => $gate->open(Arg::any()));
        when(static fn(): bool => $gate->open(Arg::same(7)));
        when(static fn(): bool => $gate->open(Arg::not(Arg::int())));
    }

    public function boundsThatCanBeMet(): void
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn(): bool => $gate->open(1))->times(2, 5);
        expect(static fn(): bool => $gate->open(2))->times(3);
        expect(static fn(): bool => $gate->open(3))->times(0, 1);
    }

    public function verificationsThatDoNotContradict(): void
    {
        $gate = Understudy::for(Gate::class);
        $gate->open(1);

        verify(static fn(): bool => $gate->open(1), times: 1);
        verify(static fn(): bool => $gate->open(9), never: true);
        verify(static fn(): bool => $gate->open(1), minimum: 1, maximum: 3);
    }

    public function oneCallIsOneSpecification(): void
    {
        $gate = Understudy::for(Gate::class);

        // A nested closure is somebody else's call, not this specification's.
        expect(static fn(): bool => $gate->open(1))->answers(
            static fn(): bool => (new \ArrayObject([]))->count() === 0,
        );
    }

    /**
     * Named bounds in either order. Read positionally, `times(maximum: 5,
     * minimum: 1)` says `(5, 1)` and correct code was reported as impossible
     * -- which costs more than a missed report, because a user cannot work
     * around it.
     */
    public function namedBoundsInEitherOrder(): void
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn(): bool => $gate->open(1))->times(maximum: 5, minimum: 1);
        expect(static fn(): bool => $gate->open(2))->times(minimum: 1, maximum: 5);
        expect(static fn(): bool => $gate->open(3))->returns(true)->times(maximum: 5, minimum: 1);
    }

    /**
     * One of the calls written here can reach a double; the others are the
     * test class's own helpers, or the receiver of the call that does.
     */
    public function oneCallOnADoubleAmongSeveralWritten(): void
    {
        $gate = Understudy::for(Gate::class);

        when(fn(): bool => $gate->open($this->identifier()));
        when(fn(): bool => $this->passThrough($gate->open(2)));
        when(fn(): bool => $this->gate()->open(3));
    }

    /**
     * A matcher that reaches its specification other than by being written
     * inside it: hoisted into a variable, stored on the object, handed over
     * as a `callable`. All of these work, and the leak rule used to call each
     * of them a matcher in a real call.
     */
    public function matchersThatArriveIndirectly(): void
    {
        $gate = Understudy::for(Gate::class);
        $any = Arg::any();
        $specification = static fn(): bool => $gate->open(Arg::int());

        when(static fn(): bool => $gate->open($any));
        when($specification);
        when($this->specificationFor($gate));
    }

    private function identifier(): int
    {
        return 1;
    }

    private function passThrough(bool $value): bool
    {
        return $value;
    }

    private function gate(): Gate
    {
        return Understudy::for(Gate::class);
    }

    private function specificationFor(Gate $gate): \Closure
    {
        return static fn(): bool => $gate->open(Arg::int());
    }
}
