<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\text\Plugin\Field\FieldType\TextItemBase;
use Drush\Commands\AutowireTrait;
use Drush\Commands\field\FieldCreateCommands;
use Drush\Commands\IoTrait;
use Drush\Event\ConsoleDefinitionsEvent;
use Drush\Event\FieldCreateFieldConfigValuesEvent;
use Drush\Event\FieldCreateInputOptionsEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(method: 'onConsoleDefinitionEvent')]
#[AsEventListener(method: 'onFieldConfigValues')]
#[AsEventListener(method: 'onInputOptions')]
final class CreateTextFieldListener
{
    use AutowireTrait;
    use IoTrait;

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected FieldTypePluginManagerInterface $fieldTypePluginManager,
    ) {
    }

    public function onConsoleDefinitionEvent(ConsoleDefinitionsEvent $event): void
    {
        $application = $event->getApplication();
        if (!$application->has(FieldCreateCommands::CREATE)) {
            return;
        }

        $command = $application->get(FieldCreateCommands::CREATE);

        $command->addOption(
            'allowed-formats',
            '',
            InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
            'Restrict which text formats are allowed, given the user has the required permissions.'
        );
    }

    public function onFieldConfigValues(FieldCreateFieldConfigValuesEvent $event): void
    {
        $this->setIo($event->getInput(), $event->getOutput());
        $values = $event->getValues();

        if (!$this->hasAllowedFormats($values['field_type'])) {
            return;
        }

        $allFormats = filter_formats();
        $allowedFormats = $this->input->getOption('allowed-formats') ?? [];

        $missingFormats = array_diff($allowedFormats, array_keys($allFormats));
        if ($missingFormats !== []) {
            throw new \InvalidArgumentException(sprintf(
                'The following text formats do not exist: %s',
                implode(', ', $missingFormats)
            ));
        }

        $values['settings']['allowed_formats'] = $allowedFormats;
        $event->setValues($values);
    }

    public function onInputOptions(FieldCreateInputOptionsEvent $event): void
    {
        $this->setIo($event->getInput(), $event->getOutput());

        if (!$this->hasAllowedFormats($this->input->getOption('field-type'))) {
            return;
        }

        $this->input->setOption(
            'allowed-formats',
            $this->input->getOption('allowed-formats') ?: $this->askAllowedFormats()
        );
    }

    protected function hasAllowedFormats(?string $fieldType = null): bool
    {
        if ($fieldType === null) {
            $defaultFieldSettings = TextItemBase::defaultFieldSettings();
        } else {
            $defaultFieldSettings = $this->fieldTypePluginManager->getDefaultFieldSettings($fieldType);
        }

        return isset($defaultFieldSettings['allowed_formats']);
    }

    /**
     * Ask for the allowed formats. Only used in case the command is run interactively.
     */
    protected function askAllowedFormats(): array
    {
        $formats = filter_formats();
        $choices = [];

        foreach ($formats as $format) {
            $choices[$format->id()] = $format->label();
        }

        return $this->io()->multiselect('Allowed formats', $choices);
    }
}
