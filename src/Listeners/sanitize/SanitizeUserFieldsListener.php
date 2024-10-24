<?php

declare(strict_types=1);

namespace Drush\Listeners\sanitize;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\sql\sanitize\SanitizeCommand;
use Drush\Event\ConsoleDefinitionsEvent;
use Drush\Event\SanitizeConfirmsEvent;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Sanitize user fields. This also an example of how to write a
 *  database sanitizer for sql:sync.
 */
#[AsEventListener(method: 'onDefinition')]
#[AsEventListener(method: 'onSanitizeConfirm')]
#[AsEventListener(method: 'onConsoleTerminate')]
final class SanitizeUserFieldsListener
{
    use AutowireTrait;

    public function __construct(
        protected Connection $database,
        protected EntityFieldManagerInterface $entityFieldManager,
        protected EntityTypeManagerInterface $entityTypeManager,
        protected FieldTypePluginManagerInterface $fieldTypePluginManager,
    ) {
    }

    public function onDefinition(ConsoleDefinitionsEvent $event): void
    {
        foreach ($event->getApplication()->all() as $id => $command) {
            if ($command->getName() === SanitizeCommand::NAME) {
                $command->addOption('allowlist-fields', null, InputOption::VALUE_REQUIRED, 'A comma delimited list of fields exempt from sanitization.');
            }
        }
    }

    public function onSanitizeConfirm(SanitizeConfirmsEvent $event): void
    {
        $event->addMessage(dt('Sanitize text fields associated with users.'));
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if ($event->getCommand()->getName() !== SanitizeCommand::NAME) {
            return;
        }

        $io = new DrushStyle($event->getInput(), $event->getOutput());

        $options = $event->getInput()->getOptions();
        $field_definitions = $this->entityFieldManager->getFieldDefinitions('user', 'user');
        $field_storage = $this->entityFieldManager->getFieldStorageDefinitions('user');
        foreach (StringUtils::csvToArray($options['allowlist-fields']) as $key) {
            unset($field_definitions[$key], $field_storage[$key]);
        }

        foreach ($field_definitions as $key => $def) {
            $execute = false;
            if (!isset($field_storage[$key]) || $field_storage[$key]->isBaseField()) {
                continue;
            }

            $table = 'user__' . $key;
            $query = $this->database->update($table);
            $name = $def->getName();
            $field_type_class = $this->fieldTypePluginManager->getPluginClass($def->getType());
            $supported_field_types = ['email', 'string', 'string_long', 'telephone', 'text', 'text_long', 'text_with_summary'];
            if (in_array($def->getType(), $supported_field_types)) {
                $value_array = $field_type_class::generateSampleValue($def);
                $value = $value_array['value'];
            } else {
                continue;
            }
            switch ($def->getType()) {
                case 'string':
                case 'string_long':
                case 'text':
                case 'text_long':
                case 'email':
                    $query->fields([$name . '_value' => $value]);
                    $execute = true;
                    break;

                case 'telephone':
                    $query->fields([$name . '_value' => '15555555555']);
                    $execute = true;
                    break;

                case 'text_with_summary':
                    $query->fields([
                        $name . '_value' => $value,
                        $name . '_summary' => $value_array['summary'],
                    ]);
                    $execute = true;
                    break;
            }
            if ($execute) {
                $query->execute();
                $this->entityTypeManager->getStorage('user')->resetCache();
                $io->success(dt('!table table sanitized.', ['!table' => $table]));
            } else {
                $io->success(dt('No text fields for users need sanitizing.', ['!table' => $table]));
            }
        }
    }
}
