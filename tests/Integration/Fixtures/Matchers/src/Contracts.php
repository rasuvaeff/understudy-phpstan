<?php

declare(strict_types=1);

namespace Fixture\Matchers;

interface Repository
{
    public function find(int $id): ?Book;

    public function rename(string $name, int $times): void;

    public function tag(string $label): bool;

    public function record(string $service, int $outcome, bool $admitted, string $attemptId): void;
}

final class Book {}
