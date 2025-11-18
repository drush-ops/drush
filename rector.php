<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Foreach_\UnusedForeachValueToArrayKeysRector;
use Rector\CodeQuality\Rector\Identical\StrlenZeroToIdenticalEmptyStringRector;
use Rector\CodeQuality\Rector\If_\CombineIfRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\CodeQuality\Rector\If_\SimplifyIfElseToTernaryRector;
use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php83: true)
    ->withParallel()
    ->withAttributesSets(phpunit: true)
    ->withImportNames(importNames: false, importShortClasses: true)
    ->withPreparedSets(deadCode: true, codeQuality: true)
    ->withTypeCoverageLevel(50)
    ->withSkip([
        StrlenZeroToIdenticalEmptyStringRector::class,
        ExplicitBoolCompareRector::class,
        IssetOnPropertyObjectToPropertyExistsRector::class,
        CombineIfRector::class,
        UnusedForeachValueToArrayKeysRector::class,
        SimplifyIfElseToTernaryRector::class,
        \Rector\CodeQuality\Rector\ClassMethod\ExplicitReturnNullRector::class,
        NullToStrictStringFuncCallArgRector::class,
        \Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector::class,
        \Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector::class,
        \Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector::class,
        \Rector\Php80\Rector\FuncCall\ClassOnObjectRector::class,
        \Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector::class,
        \Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector::class,
        \Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector::class,
        \Rector\DeadCode\Rector\Foreach_\RemoveUnusedForeachKeyRector::class
    ]);
