<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Commands\config\ConfigCommands;

#[Attribute(Attribute::TARGET_METHOD)]
class InteractConfigName
{
    /**
     * @param string $argumentName
     *   The name of the argument which specifies the config ID.
     */
    public function __construct(
        public string $argumentName = 'config_name'
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $commandInfo->addAnnotation(ConfigCommands::INTERACT_CONFIG_NAME, $attribute->newInstance()->argumentName);
    }
}
