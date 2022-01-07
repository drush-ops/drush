<?php

declare(strict_types=1);

use Rector\Core\Configuration\Option;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\FunctionLike\ParamTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\FunctionLike\ReturnTypeDeclarationRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();
    $containerConfigurator->import(SetList::CODE_QUALITY);
    $parameters->set(Option::AUTO_IMPORT_NAMES, true);
    $parameters->set(Option::IMPORT_SHORT_CLASSES, false);
    $src = [__DIR__ . '/src'];
    $parameters->set(Option::SKIP, [
        \Rector\CodeQuality\Rector\Identical\StrlenZeroToIdenticalEmptyStringRector::class => $src,
        \Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector::class => $src,
        \Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector::class => $src,
        \Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector::class => $src,
        \Rector\CodeQuality\Rector\If_\CombineIfRector::class => $src,
        \Rector\CodeQuality\Rector\Foreach_\UnusedForeachValueToArrayKeysRector::class => $src,
    ]);
    $services = $containerConfigurator->services();
    $services->set(ParamTypeDeclarationRector::class);
    $services->set(ReturnTypeDeclarationRector::class);
};