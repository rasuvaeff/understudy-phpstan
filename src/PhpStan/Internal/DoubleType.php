<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Internal;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * `Understudy::for(A::class, B::class)` is a double of both, and its type
 * says so: `A&B`.
 *
 * The core declares `@return T` for the first argument, and has no way to
 * name the rest — a variadic `class-string ...$interfaces` carries no
 * template. Without this the methods of `B` do not exist for the analyser,
 * on a double built precisely to have them.
 *
 * Only for the spelling whose classes are known without guessing: two or
 * more positional arguments, every one a written-out `Name::class`, the
 * first a class or interface PHPStan knows and every other an interface — the
 * shape the core accepts. Anything else is left to the core's declaration,
 * which is right for the first argument and silent about the rest.
 *
 * @internal
 */
final readonly class DoubleType
{
    public function __construct(private ReflectionProvider $reflection) {}

    /**
     * @param array<Arg> $arguments
     */
    public function of(array $arguments): ?Type
    {
        if (\count($arguments) < 2) {
            return null;
        }

        $names = [];

        foreach ($arguments as $argument) {
            $name = $this->literalClass($argument);

            if ($name === null || !$this->reflection->hasClass($name)) {
                return null;
            }

            $names[] = $name;
        }

        $types = [];

        foreach ($names as $position => $name) {
            if ($position > 0 && !$this->reflection->getClass($name)->isInterface()) {
                return null;
            }

            $types[] = new ObjectType($name);
        }

        $double = TypeCombinator::intersect(...$types);

        return $double instanceof NeverType ? null : $double;
    }

    /**
     * @return non-empty-string|null
     */
    private function literalClass(Arg $argument): ?string
    {
        $value = $argument->value;

        if ($argument->unpack
            || $argument->name instanceof \PhpParser\Node\Identifier
            || !$value instanceof ClassConstFetch
            || !$value->class instanceof Name
            || !$value->name instanceof Identifier
            || $value->name->toLowerString() !== 'class'
            || $value->class->isSpecialClassName()
        ) {
            return null;
        }

        $resolved = ltrim(ResolvedName::of($value->class), '\\');

        return $resolved === '' ? null : $resolved;
    }
}
