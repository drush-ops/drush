<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drupal\Core\Messenger\MessengerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Drupal\DrupalUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Replaces MessengerCommands for non-Annotated commands. Our terminate event fires for those too,
 */
#[AsEventListener(method: 'onConsoleCommandEvent')]
#[AsEventListener(method: 'onConsoleTerminateEvent')]
final class MessengerListener
{
    use AutowireTrait;

    public function __construct(
        protected readonly MessengerInterface $messenger,
        protected readonly LoggerInterface $logger,
    ) {
    }

    public function onConsoleCommandEvent(ConsoleCommandEvent $event): void
    {
        self::log();
    }

    public function onConsoleTerminateEvent(ConsoleTerminateEvent $event): void
    {
        self::log();
    }

    public function log(): void
    {
        $prefix = 'Message: ';
        foreach ($this->messenger->messagesByType(MessengerInterface::TYPE_ERROR) as $message) {
            $this->logger->error($prefix . DrupalUtil::drushRender($message));
        }
        foreach ($this->messenger->messagesByType(MessengerInterface::TYPE_WARNING) as $message) {
            $this->logger->warning($prefix . DrupalUtil::drushRender($message));
        }
        foreach ($this->messenger->messagesByType(MessengerInterface::TYPE_STATUS) as $message) {
            $this->logger->notice($prefix . DrupalUtil::drushRender($message));
        }
        $this->messenger->deleteAll();
    }
}
