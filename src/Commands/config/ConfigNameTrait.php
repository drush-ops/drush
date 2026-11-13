<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

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
}
