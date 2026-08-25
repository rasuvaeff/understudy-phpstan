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
}
