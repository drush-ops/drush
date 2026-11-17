<?php

declare(strict_types=1);

namespace Drush\EventSubscriber\sanitize;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Event\SanitizeConfirmsEvent;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sanitizes the text fields added to users.
 *
 * Registered only when the user module is installed.
 */
final class SanitizeUserFieldsSubscriber implements EventSubscriberInterface
{
    use SanitizeOptionsTrait;
    use StringTranslationTrait;

    private const array TEXT_TYPES = ['email', 'string', 'string_long', 'text', 'text_long'];

    public function __construct(
        private readonly Connection $database,
        private readonly EntityFieldManagerInterface $entityFieldManager,
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly FieldTypePluginManagerInterface $fieldTypePluginManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onCommand',
            SanitizeConfirmsEvent::class => 'onSanitizeConfirm',
            ConsoleEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $this->addSanitizeOptions($event, [
            new InputOption('allowlist-fields', null, InputOption::VALUE_REQUIRED, 'A comma delimited list of fields exempt from sanitization.'),
        ]);
    }

    public function onSanitizeConfirm(SanitizeConfirmsEvent $event): void
    {
        $event->addMessage((string) $this->t('Sanitize text fields associated with users.'));
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if (!$this->isSanitizing($event)) {
            return;
        }

        $output = $event->getOutput();
        $allowlist = StringUtils::csvToArray($event->getInput()->getOption('allowlist-fields'));
        $storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions('user');
        $sanitized = false;
        foreach ($this->entityFieldManager->getFieldDefinitions('user', 'user') as $name => $definition) {
            if (in_array($name, $allowlist) || !isset($storage_definitions[$name]) || $storage_definitions[$name]->isBaseField()) {
                continue;
            }
            $type = $definition->getType();
            if (!in_array($type, self::TEXT_TYPES)) {
                continue;
            }
            $sample = $this->fieldTypePluginManager->getPluginClass($type)::generateSampleValue($definition);

            $table = 'user__' . $name;
            $this->database->update($table)->fields([$name . '_value' => $sample['value']])->execute();
            $sanitized = true;
            $output->writeln((string) $this->t('@table table sanitized.', ['@table' => $table]));
        }

        if ($sanitized) {
            $this->entityTypeManager->getStorage('user')->resetCache();
        } else {
            $output->writeln((string) $this->t('No text fields for users need sanitizing.'));
        }
    }
}
