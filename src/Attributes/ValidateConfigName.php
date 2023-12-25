<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateConfigName extends ValidatorBase implements ValidatorInterface
{
    /**
     * @param string $argumentName
     *   The name of the argument which specifies the config ID.
     */
    public function __construct(
        public string $argumentName = 'config_name'
    ) {
    }

    public function validate(CommandData $commandData)
    {
        $configName = $commandData->input()->getArgument($this->argumentName);
        $config = \Drupal::config($configName);
        if ($config->isNew()) {
            $msg = dt('Config !name does not exist', ['!name' => $configName]);
            return new CommandError($msg);
        }
    }
}
