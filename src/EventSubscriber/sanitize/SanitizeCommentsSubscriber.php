<?php

declare(strict_types=1);

namespace Drush\EventSubscriber\sanitize;

use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Event\SanitizeConfirmsEvent;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Removes comment display names and emails.
 *
 * Registered only when the comment module is installed.
 */
final class SanitizeCommentsSubscriber implements EventSubscriberInterface
{
    use SanitizeOptionsTrait;
    use StringTranslationTrait;

    public function __construct(
        private readonly Connection $database,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SanitizeConfirmsEvent::class => 'onSanitizeConfirm',
            ConsoleEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onSanitizeConfirm(SanitizeConfirmsEvent $event): void
    {
        $event->addMessage((string) $this->t('Remove comment display names and emails.'));
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if (!$this->isSanitizing($event)) {
            return;
        }

        // Comments by anonymous users.
        $this->database->update('comment_field_data')
            ->fields([
                'name' => 'Anonymous',
                'mail' => '',
                'homepage' => 'http://example.com',
            ])
            ->condition('uid', 0)
            ->execute();

        // Comments by authenticated users.
        $this->database->update('comment_field_data')
            ->expression('name', "CONCAT('User', uid)")
            ->expression('mail', "CONCAT('user+', uid, '@example.com')")
            ->fields(['homepage' => 'http://example.com'])
            ->condition('uid', 1, '>=')
            ->execute();
        $event->getOutput()->writeln((string) $this->t('Comment display names and emails removed.'));
    }
}
