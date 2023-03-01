<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Messenger\MessengerInterface;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drush\Drupal\DrupalUtil;

final class MessengerCommands extends DrushCommands
{
    protected MessengerInterface $messenger;

    /**
     * @inheritDoc
     */
    public function __construct(MessengerInterface $messenger)
    {
        $this->messenger = $messenger;
    }

    #[CLI\Hook(type: HookManager::PRE_COMMAND_HOOK, target: '*')]
    public function pre(): void
    {
        self::log();
    }

    #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: '*')]
    public function post(): void
    {
        self::log();
    }

    public function log(): void
    {
        if (!\Drupal::hasService('messenger')) {
            return;
        }

        $prefix = 'Message: ';
        foreach ($this->messenger->messagesByType(MessengerInterface::TYPE_ERROR) as $message) {
            $this->logger()->error($prefix . DrupalUtil::drushRender($message));
        }
        foreach ($this->messenger->messagesByType(MessengerInterface::TYPE_WARNING) as $message) {
            $this->logger()->warning($prefix . DrupalUtil::drushRender($message));
        }
        foreach ($this->messenger->messagesByType(MessengerInterface::TYPE_STATUS) as $message) {
            $this->logger()->notice($prefix . DrupalUtil::drushRender($message));
        }
        $this->messenger->deleteAll();
    }
}
