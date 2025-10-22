<?php

declare(strict_types=1);

namespace Drush\Listeners\sanitize;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
final class SanitizeCommentsListener
{
    use AutowireTrait;

    public function __construct(
        protected Connection $database,
        protected ModuleHandlerInterface $moduleHandler,
        protected LoggerInterface $logger,
    ) {
    }

    public function onSanitizeConfirm(SanitizeConfirmsEvent $event): void
    {
        if ($this->applies()) {
            $event->addMessage(dt('Remove comment display names and emails.'));
        }
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if ($event->getCommand()->getName() !== 'sql:sanitize'  || $event->getExitCode() || !$this->applies()) {
            return;
        }

        //Update to anon.
        $this->database->update('comment_field_data')
            ->fields([
                'name' => 'Anonymous',
                'mail' => '',
                'homepage' => 'http://example.com'
            ])
            ->condition('uid', 0)
            ->execute();

        // Update auth.
        $this->database->update('comment_field_data')
            ->expression('name', "CONCAT('User', uid)")
            ->expression('mail', "CONCAT('user+', uid, '@example.com')")
            ->fields(['homepage' => 'http://example.com'])
            ->condition('uid', 1, '>=')
            ->execute();
        $this->logger->notice(dt('Comment display names and emails removed.'));
    }

    private function applies(): bool
    {
        return $this->moduleHandler->moduleExists('comment');
    }
}
