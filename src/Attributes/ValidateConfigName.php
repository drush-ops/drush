<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Drupal\Commands\config\ConfigCommands;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateConfigName
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
        $commandInfo->addAnnotation(ConfigCommands::VALIDATE_CONFIG_NAME, $attribute->newInstance()->argumentName);
    }
}
