<?php

declare(strict_types=1);

namespace Fixture\Matchers;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

final class Correct
{
    public function run(): void
    {
        $repository = Understudy::for(Repository::class);

        when(fn() => $repository->find(Arg::int()))->returns(new Book());
        when(fn() => $repository->find(Arg::any()))->returns(null);
        when(fn() => $repository->tag(Arg::string()))->returns(true);
        when(fn() => $repository->rename(Arg::string(), Arg::int()));
    }

    /**
     * `Arg::rest()` legitimately passes fewer arguments than the contract
     * declares (understudy 0.4): both the arity report and the matcher's
     * own argument report must go quiet here.
     */
    public function prefixSpecification(): void
    {
        $repository = Understudy::for(Repository::class);

        when(fn() => $repository->record('svc', Arg::rest()));
    }

    /**
     * A captor's `->capture()` is a matcher written as a method call on the
     * `Captor` that `Arg::captor()` handed back.
     */
    public function captorSpecification(): void
    {
        $repository = Understudy::for(Repository::class);
        $ids = Arg::captor();

        when(fn() => $repository->find($ids->capture()))->returns(null);
    }
}
