<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodeQuality\Rector\Foreach_\UnusedForeachValueToArrayKeysRector;
use Rector\CodeQuality\Rector\Identical\StrlenZeroToIdenticalEmptyStringRector;
use Rector\CodeQuality\Rector\If_\CombineIfRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\CodeQuality\Rector\If_\SimplifyIfElseToTernaryRector;
use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\ClassConst\FinalizePublicClassConstantRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $config): void {
    $config->importNames();
    $config->importShortClasses(false);

    $config->paths([
        __DIR__ . '/src',
    ]);

    $config->sets([
        SetList::CODE_QUALITY,
        SetList::PHP_81,
        SetList::DEAD_CODE
    ]);

    $config->skip([
        StrlenZeroToIdenticalEmptyStringRector::class,
        ExplicitBoolCompareRector::class,
        IssetOnPropertyObjectToPropertyExistsRector::class,
        CallableThisArrayToAnonymousFunctionRector::class,
        CombineIfRector::class,
        UnusedForeachValueToArrayKeysRector::class,
        SimplifyIfElseToTernaryRector::class,
        FinalizePublicClassConstantRector::class,
        NullToStrictStringFuncCallArgRector::class,
        \Rector\Php81\Rector\Array_\FirstClassCallableRector::class,
        \Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector::class,
        \Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector::class,
        \Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector::class,
        \Rector\DeadCode\Rector\Foreach_\RemoveUnusedForeachKeyRector::class
    ]);
};
