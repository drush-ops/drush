<?php

declare(strict_types=1);

namespace Drush\Commands\queue;

use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

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
     * Call this at the top of execute() instead of relying on a validation
     * attribute, so the command behaves the same under any console runner.
     */
    protected function validateQueueName(string $name): void
    {
        if (!array_key_exists($name, $this->getQueues())) {
            throw new \InvalidArgumentException(sprintf('Queue not found: %s', $name));
        }
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            $suggestions->suggestValues(array_keys($this->getQueues()));
        }
    }
}
