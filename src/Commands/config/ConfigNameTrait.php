<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

/**
 * Helpers for commands that take a config object name.
 *
 * Classes using this trait inject ConfigFactoryInterface as $this->configFactory.
 */
trait ConfigNameTrait
{
    use StringTranslationTrait;

    protected ConfigFactoryInterface $configFactory;

    /**
     * Shell completion for the config name argument.
     *
     * @return string[]
     */
    public function suggestConfigNames(): array
    {
        return $this->configFactory->listAll();
    }

    /**
     * Throws when no config object of that name is saved.
     */
    protected function requireConfig(string $name): void
    {
        if ($this->configFactory->get($name)->isNew()) {
            throw new \InvalidArgumentException((string) $this->t('Config @name does not exist', ['@name' => $name]));
        }
    }

    /**
     * @deprecated in drush:14.0.0 and is removed from drush:15.0.0. Invokable
     *   commands complete with suggestedValues: [self::class, 'suggestConfigNames'].
     */
    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('config_name')) {
            $suggestions->suggestValues($this->configFactory->listAll());
        }
    }
}
