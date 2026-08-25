<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\PhpStan;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Type;
use Rasuvaeff\Understudy\ExpectBuilder;
use Rasuvaeff\Understudy\PhpStan\Internal\BuilderType;
use Rasuvaeff\Understudy\WhenBuilder;

/**
 * Gives the `when()` and `expect()` functions the builder type they really
 * produce, so that `returns()` and `answers()` are checked against the
 * method being specified rather than against `mixed`.
 *
 * @internal
 */
final class BuilderReturnType implements DynamicFunctionReturnTypeExtension
{
    /** @var array<lowercase-string, class-string> */
    private const array BUILDERS = [
        'rasuvaeff\understudy\when' => WhenBuilder::class,
        'rasuvaeff\understudy\expect' => ExpectBuilder::class,
    ];

    #[\Override]
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return isset(self::BUILDERS[strtolower($functionReflection->getName())]);
    }

    #[\Override]
    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        Scope $scope,
    ): ?Type {
        $builder = self::BUILDERS[strtolower($functionReflection->getName())] ?? null;

        if ($builder === null) {
            return null;
        }

        return BuilderType::of($functionCall->getArgs(), $builder, $scope);
    }
}
