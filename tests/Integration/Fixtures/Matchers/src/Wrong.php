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
}
