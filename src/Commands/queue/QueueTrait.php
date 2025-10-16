<?php

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

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            $suggestions->suggestValues(array_keys($this->getQueues()));
        }
    }
}
