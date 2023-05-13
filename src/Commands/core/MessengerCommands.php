<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Messenger\MessengerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drupal\DrupalUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class MessengerCommands extends DrushCommands
{
    public function __construct(protected MessengerInterface $messenger)
    {
    }

    public static function create(ContainerInterface $container): self
    {
        $commandHandler = new static(
            $container->get('messenger')
        );

        return $commandHandler;
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
