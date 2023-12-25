<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateQueueName extends ValidatorBase implements ValidatorInterface
{
    private static array $queues = [];

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
        if (!array_key_exists($queueName, self::getQueues())) {
            $msg = dt('Queue not found: !name', ['!name' => $queueName]);
            return new CommandError($msg);
        }
    }

    public static function getQueues()
    {
        if (!isset(static::$queues)) {
            static::$queues = [];
            foreach (\Drupal::service('plugin.manager.queue_worker')->getDefinitions() as $name => $info) {
                static::$queues[$name] = $info;
            }
        }
        return static::$queues;
    }
}
