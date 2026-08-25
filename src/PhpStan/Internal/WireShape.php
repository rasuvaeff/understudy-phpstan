<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Internal;

use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * The precise shape `wire()` hands back for a named class.
 *
 * The core declares `array{sut: object, doubles: array<string, object>}`,
 * which is all it can say without knowing the class. Given a literal
 * `Sut::class` the constructor is readable, so `$wired['sut']` can be the
 * class itself and `$wired['doubles']['repository']` the contract it stands
 * for — and a key the constructor has no parameter for becomes an error
 * instead of a silent `object`.
 *
 * A dynamic class-string is left alone: the core's declaration is then the
 * honest answer, and guessing would be worse than saying less.
 *
 * @internal
 */
final readonly class WireShape
{
    public function __construct(private ReflectionProvider $reflectionProvider) {}

    /**
     * @param array<Arg> $arguments
     */
    public function of(array $arguments, Scope $scope): ?Type
    {
        $class = $this->namedClass($arguments[0]->value ?? null, $scope);

        if ($class === null) {
            return null;
        }

        if (!$this->reflectionProvider->hasClass($class)) {
            return null;
        }

        $reflection = $this->reflectionProvider->getClass($class);

        if (!$reflection->hasConstructor()) {
            // Nothing to read. The core's own declaration is then the honest
            // answer, and a shape of ours would only be a narrower guess.
            return null;
        }

        $doubles = ConstantArrayTypeBuilder::createEmpty();
        $found = false;

        // The first variant, not the argument-based selector: `wire()` is
        // handed a class name and no constructor arguments at all, so there
        // is nothing to select a variant by. A constructor with more than
        // one variant does not exist in userland code.
        $variants = $reflection->getConstructor()->getVariants();
        $parameters = $variants[0]->getParameters();

        foreach ($parameters as $parameter) {
            $objectType = $this->soleObjectType($parameter->getType());

            if (!$objectType instanceof \PHPStan\Type\Type) {
                continue;
            }

            $doubles->setOffsetValueType(new ConstantStringType($parameter->getName()), $objectType);
            $found = true;
        }

        if (!$found) {
            return null;
        }

        $shape = ConstantArrayTypeBuilder::createEmpty();
        $shape->setOffsetValueType(new ConstantStringType('sut'), new ObjectType($class));
        $shape->setOffsetValueType(new ConstantStringType('doubles'), $doubles->getArray());

        return $shape->getArray();
    }

    /**
     * The contract a constructor parameter stands for, when it is one object
     * type and nothing else. A union, a scalar or an untyped parameter is
     * not a double, and `wire()` does not make one for it.
     */
    private function soleObjectType(Type $type): ?Type
    {
        $classNames = $type->getObjectClassNames();

        return \count($classNames) === 1 ? new ObjectType($classNames[0]) : null;
    }

    /**
     * The class a literal `Sut::class` or `'Sut'` names.
     */
    private function namedClass(mixed $expression, Scope $scope): ?string
    {
        if (!$expression instanceof \PhpParser\Node\Expr) {
            return null;
        }

        $strings = $scope->getType($expression)->getConstantStrings();

        if (\count($strings) !== 1) {
            return null;
        }

        $value = $strings[0]->getValue();

        return $value === '' ? null : $value;
    }
}
