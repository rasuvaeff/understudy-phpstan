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
}
