<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateQueueName extends ValidatorBase implements ValidatorInterface
{
    /**
     * @param string $argumentName
     *   The name of the argument which specifies the queue name.
     */
    public function __construct(
        public string $argumentName = 'queue_name'
    ) {
    }

    public function validate(CommandData $commandData)
    {
        $queueName = $commandData->input()->getArgument($this->argumentName);
        if (!in_array($queueName, self::getQueues())) {
            $msg = dt('Queue not found: !name', ['!name' => $queueName]);
            return new CommandError($msg);
        }
    }

    public static function getQueues(): array
    {
        return array_keys(\Drupal::service('plugin.manager.queue_worker')->getDefinitions());
    }
}
