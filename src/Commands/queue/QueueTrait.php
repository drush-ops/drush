<?php

declare(strict_types=1);

namespace Drush\Commands\queue;

use Symfony\Component\Console\Completion\CompletionInput;

trait QueueTrait
{
    protected static array $queues;

    public function getQueues(): array
    {
        if (!isset(static::$queues)) {
            static::$queues = [];
            foreach ($this->workerManager->getDefinitions() as $name => $info) {
                static::$queues[$name] = $info;
            }
        }
        return static::$queues;
    }

    /**
     * Throws when no queue worker is defined for the given queue name.
     *
     * Call this at the top of __invoke() instead of relying on a validation
     * attribute, so the command behaves the same under any console runner.
     */
    protected function validateQueueName(string $name): void
    {
        if (!array_key_exists($name, $this->getQueues())) {
            throw new \InvalidArgumentException(sprintf('Queue not found: %s', $name));
        }
    }

    /**
     * Shell completion for the queue name argument.
     *
     * Referenced from #[Argument(suggestedValues: [self::class, 'suggestQueueNames'])].
     */
    public function suggestQueueNames(CompletionInput $input): array
    {
        return array_keys($this->getQueues());
    }
}
