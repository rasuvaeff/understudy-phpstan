<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Internal;

use PhpParser\Node\Name;

/**
 * The name as the parser resolved it, falling back to what was written.
 *
 * `use Rasuvaeff\Understudy\Understudy;` then `Understudy::when()` is written
 * as the bare name `Understudy`, and only the resolver knows which class that
 * is. Reading the written name alone would silently skip the static form —
 * which is the form Pest users are told to reach for, because Pest owns the
 * global `expect()`.
 *
 * @internal
 */
final class ResolvedName
{
    private function __construct() {}

    public static function of(Name $name): string
    {
        // Read out of the attribute bag rather than through getAttribute(),
        // which is declared `mixed`: assigning that needs a `@var`
        // annotation to satisfy psalm, and rector then removes the
        // annotation as useless. An array offset narrows on its own, so
        // neither gate has an opinion.
        $attributes = $name->getAttributes();

        return isset($attributes['resolvedName']) && \is_string($attributes['resolvedName'])
            ? $attributes['resolvedName']
            : $name->toString();
    }
}
