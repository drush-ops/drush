<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Consolidation\AnnotatedCommand\Attributes\AttributeInterface;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Drush;

abstract class ValidatorBase
{
    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $instance = $attribute->newInstance();
        $hookManager = Drush::getContainer()->get('hookManager');
        $hookManager->add(
            // Use a Closure to acquire $commandData.
            fn(CommandData $commandData) => $instance->validate($commandData),
            $hookManager::ARGUMENT_VALIDATOR,
            $commandInfo->getName()
        );
    }
}
