<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Rasuvaeff\Understudy\ExpectBuilder;
use Rasuvaeff\Understudy\PhpStan\Internal\BuilderType;
use Rasuvaeff\Understudy\PhpStan\Internal\WireShape;
use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Understudy\WhenBuilder;

/**
 * Every return type on `Understudy` that depends on what was passed in.
 *
 * `when()` and `expect()` in their collision-free static form, which Pest
 * users are told to reach for because Pest owns the global `expect()` — an
 * extension that only knew the free functions would be silent exactly for
 * them. And `wire()`, whose shape is readable from the named class's
 * constructor.
 *
 * @internal
 */
final readonly class StaticBuilderReturnType implements DynamicStaticMethodReturnTypeExtension
{
    /** @var array<lowercase-string, class-string> */
    private const array BUILDERS = [
        'when' => WhenBuilder::class,
        'expect' => ExpectBuilder::class,
    ];

    public function __construct(private WireShape $wireShape) {}

    #[\Override]
    public function getClass(): string
    {
        return Understudy::class;
    }

    #[\Override]
    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        $name = strtolower($methodReflection->getName());

        return $name === 'wire' || isset(self::BUILDERS[$name]);
    }

    #[\Override]
    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): ?Type {
        $name = strtolower($methodReflection->getName());
        $arguments = $methodCall->getArgs();

        if ($name === 'wire') {
            return $this->wireShape->of($arguments, $scope);
        }

        $builder = self::BUILDERS[$name] ?? null;

        return $builder === null ? null : BuilderType::of($arguments, $builder, $scope);
    }
}
