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
 * Truncates the sessions table. The smallest example of a sanitizer.
 */
final class SanitizeSessionsSubscriber implements EventSubscriberInterface
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
        if ($this->applies()) {
            $event->addMessage((string) $this->t('Truncate sessions table.'));
        }
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if (!$this->isSanitizing($event) || !$this->applies()) {
            return;
        }
        $this->database->truncate('sessions')->execute();
        $event->getOutput()->writeln((string) $this->t('Sessions table truncated.'));
    }

    /**
     * Core creates the table on the first session write.
     */
    private function applies(): bool
    {
        return $this->database->schema()->tableExists('sessions');
    }
}
