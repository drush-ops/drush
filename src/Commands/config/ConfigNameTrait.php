<?php

namespace Drush\Commands\config;

use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

trait ConfigNameTrait
{
    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('config_name')) {
            $suggestions->suggestValues($this->configFactory->listAll());
        }
    }
}
