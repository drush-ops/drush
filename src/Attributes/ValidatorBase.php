<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Consolidation\AnnotatedCommand\Attributes\AttributeInterface;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Drush;

abstract class ValidatorBase
{
    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        /** @var HookManager $hookManager */
        $hookManager = Drush::getContainer()->get('hookManager');
        $hookManager->add(
        // Use a Closure to acquire $commandData and $args.
            fn(CommandData $commandData) => static::validate($commandData, ...$attribute->getArguments()),
            $hookManager::ARGUMENT_VALIDATOR,
            $commandInfo->getName()
        );
    }
}
