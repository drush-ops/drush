<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateConfigName extends ValidatorBase
{
    /**
     * @param string $argumentName
     *   The name of the argument which specifies the config ID.
     */
    public function __construct(
        public string $argumentName = 'config_name'
    ) {
    }

    public static function validate(CommandData $commandData, \ReflectionAttribute $attribute)
    {
        $argumentName = $attribute->newInstance()->argumentName;
        $configName = $commandData->input()->getArgument($argumentName);
        $config = \Drupal::config($configName);
        if ($config->isNew()) {
            $msg = dt('Config !name does not exist', ['!name' => $configName]);
            return new CommandError($msg);
        }
    }
}
