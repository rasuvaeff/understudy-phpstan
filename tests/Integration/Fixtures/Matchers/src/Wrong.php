<?php

declare(strict_types=1);

namespace Fixture\Matchers;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

final class Wrong
{
    public function siblingArgumentIsStillChecked(): void
    {
        $repository = Understudy::for(Repository::class);

        when(fn() => $repository->rename(Arg::any(), 'not an int'));
    }

    public function undefinedMethodIsStillReported(): void
    {
        $repository = Understudy::for(Repository::class);

        when(fn() => $repository->missing(Arg::any()));
    }

    public function matcherOfTheWrongKind(): void
    {
        $repository = Understudy::for(Repository::class);

        when(fn() => $repository->tag(Arg::int()));
    }

    public function matcherOutsideASpecification(): void
    {
        $repository = Understudy::for(Repository::class);

        $repository->find(Arg::int());
    }

    /**
     * A real call ending in `Arg::rest()` is under-arity for real — the
     * engine answers it with `ArgumentCountError`. Statically the line is
     * reported once, as the leak it is: the arity half is quiet by design
     * wherever `Arg::rest()` is the last argument, because the leak report
     * names the actual mistake and the arity report would only restate it.
     */
    public function underArityOutsideASpecification(): void
    {
        $repository = Understudy::for(Repository::class);

        $repository->record('svc', Arg::rest());
    }

    public function captureOutsideASpecification(): void
    {
        $repository = Understudy::for(Repository::class);
        $ids = Arg::captor();

        $repository->find($ids->capture());
    }
}
