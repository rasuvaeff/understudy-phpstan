<?php

declare(strict_types=1);

namespace Fixture\Matchers;

interface Repository
{
    public function find(int $id): ?Book;

    public function rename(string $name, int $times): void;

    public function tag(string $label): bool;
}

final class Book {}
