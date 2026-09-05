<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan\Internal;

use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

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
            // `?Contract` is the same double — the core builds one rather than
            // passing null wherever it can — so null is dropped before the
            // type is read. Without this the union answered no class name, no
            // shape was built for the key, and `$wired['doubles']['gate']`
            // stayed `object`: "Call to an undefined method object::find()" on
            // working code, with the docblock below promising the opposite.
            $type = TypeCombinator::removeNull($parameter->getType());

            if ($this->isUndecidable($type)) {
                // The core refuses to wire this class at all — the call
                // throws `CannotWire` before it returns anything — so there
                // is no shape to describe, not even for the parameters that
                // would have been fine.
                return null;
            }

            $contract = $this->contractType($type);

            if (!$contract instanceof \PHPStan\Type\Type) {
                continue;
            }

            $doubles->setOffsetValueType(new ConstantStringType($parameter->getName()), $contract);
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
     * The contract a constructor parameter stands for.
     *
     * One object type is one double, and `?Contract` is the same double: the
     * core builds one rather than passing null wherever it can. An
     * intersection is one double standing for every contract in it — the core
     * sends it down the same branch a single contract takes — so the key
     * keeps the intersection, and both halves stay callable on it. Reporting
     * `A&B` as `A` was narrower than the object the call hands back, and
     * dropping the parameter made its key a missing offset on working code.
     *
     * A scalar or an untyped parameter is not a double, and `wire()` does not
     * make one for it.
     */
    private function contractType(Type $type): ?Type
    {
        $classNames = $type->getObjectClassNames();

        if (\count($classNames) === 1) {
            return new ObjectType($classNames[0]);
        }

        return $type instanceof IntersectionType ? $type : null;
    }

    /**
     * Whether this parameter makes the core refuse the whole class.
     *
     * A union naming more than one object type is undecidable by design:
     * `Wire::resolve()` throws `CannotWire::undecidableParameter` rather than
     * guess which contract to double, and the exception aborts the call
     * before any key exists. An intersection is not that — it names several
     * contracts for ONE double.
     */
    private function isUndecidable(Type $type): bool
    {
        return \count($type->getObjectClassNames()) > 1 && !$type instanceof IntersectionType;
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
