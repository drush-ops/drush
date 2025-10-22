<?php

declare(strict_types=1);

namespace Drush\Listeners\sanitize;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Event\SanitizeConfirmsEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Sanitize emails and passwords. This also an example of how to write a
 *  database sanitizer.
 */
#[AsEventListener(method: 'onSanitizeConfirm')]
#[AsEventListener(method: 'onConsoleTerminate')]
final class SanitizeSessionsListener
{
    use AutowireTrait;

    public function __construct(
        protected Connection $database,
        protected EntityTypeManagerInterface $entityTypeManager,
        protected LoggerInterface $logger,
    ) {
    }

    public function onSanitizeConfirm(SanitizeConfirmsEvent $event): void
    {
        if ($this->applies()) {
            $event->addMessage(dt('Truncate sessions table.'));
        }
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if ($event->getCommand()->getName() !== 'sql:sanitize'  || $event->getExitCode() || !$this->applies()) {
            return;
        }

        $this->database->truncate('sessions')->execute();
        $this->entityTypeManager->getStorage('user')->resetCache();
        $this->logger->notice('Sessions table truncated.');
    }

    private function applies(): bool
    {
        return $this->database->schema()->tableExists('sessions');
    }
}
