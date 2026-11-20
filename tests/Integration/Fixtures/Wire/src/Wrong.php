<?php

declare(strict_types=1);

namespace Fixture\Wire;

use Rasuvaeff\Understudy\Understudy;

final class Wrong
{
    public function aKeyTheConstructorHasNoParameterFor(): void
    {
        $wired = Understudy::wire(Checkout::class);

        $wired['doubles']['repository']->find(1);
    }

    public function aMethodTheContractDoesNotHave(): void
    {
        $wired = Understudy::wire(Checkout::class);

        $wired['doubles']['clock']->tick();
    }

    /**
     * A union naming two object types makes the core refuse the whole class,
     * so no shape describes this call — the core's own declaration stands and
     * the double is a plain `object`.
     *
     * Reporting a missing `either` key instead, which is what dropping the
     * parameter used to do, named the wrong mistake: the key is not missing,
     * the call never returns.
     */
    public function aUnionTheCoreRefusesToWire(): void
    {
        $wired = Understudy::wire(Ambiguous::class);

        $wired['doubles']['either']->now();
    }


    /**
     * A parameter passed through `$overrides` takes the given value, and the
     * core builds no double for it.
     */
    public function anOverriddenParameterHasNoDouble(Clock $clock): void
    {
        $wired = Understudy::wire(Checkout::class, ['clock' => $clock]);

        $wired['doubles']['clock']->now();
    }

    /**
     * Keys not known statically may name any parameter, so every key may be
     * missing.
     *
     * @param array<string, mixed> $overrides
     */
    public function aNonLiteralOverridesArrayMakesEveryKeyOptional(array $overrides): void
    {
        $wired = Understudy::wire(Checkout::class, $overrides);

        $wired['doubles']['books']->find(1);
    }

    public function aFinalClassWithADefaultMayKeepItsDefault(): void
    {
        $wired = Understudy::wire(Stamped::class);

        $wired['doubles']['fallback']->now();
    }

    public function aVariadicTailIsNeverDoubled(): void
    {
        $wired = Understudy::wire(Fanout::class);

        $wired['doubles']['auditors']->audit();
    }

    /**
     * With nothing doubled the shape still says so, rather than falling back
     * to the core's `array<string, object>`, where any key passes.
     */
    public function aVariadicOnlyConstructorHasNoDoubles(): void
    {
        $wired = Understudy::wire(Gathering::class);

        $wired['doubles']['auditors']->audit();
    }
}
