<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drush\Attributes\ValidateQueueName;
use Drush\Commands\AutowireTrait;
use Drush\Utils\StringUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class ValidateQueueNameListener
{
    use AutowireTrait;

    public function __construct(
        protected QueueWorkerManagerInterface $workerManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     *  This subscriber affects commands which put #[ValidateQueueName] on the *class*.
     *  Method usages are enforced by Annotated Command still.
     */
    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        // Support invokable commands (Symfony Console 7.4+).
        $code = method_exists($command, 'getCode') && $command->getCode() ? $command->getCode() : $command;
        $reflection = new \ReflectionObject($code);
        $attributes = $reflection->getAttributes(ValidateQueueName::class);
        if (empty($attributes)) {
            return;
        }

        /** @var ValidateQueueName $instance */
        $instance = $attributes[0]->newInstance();
        if (!$command->getDefinition()->hasArgument($instance->argumentName)) {
            return;
        }
        $names = StringUtils::csvToArray($event->getInput()->getArgument($instance->argumentName));
        $missing = array_diff($names, array_keys($this->getQueues()));
        if ($missing) {
            $this->logger->error('Queue name(s) not found: {names}', ['names' => implode(', ', $missing)]);
            $event->disableCommand();
        }
    }

    // In 14.x this is in QueueTrait
    public static function getQueues(): array
    {
        return array_keys(\Drupal::service('plugin.manager.queue_worker')->getDefinitions());
    }
}
