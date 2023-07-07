<?php

namespace Drush\Commands\field;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\text\Plugin\Field\FieldType\TextItemBase;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FieldTextHooks extends DrushCommands
{
    use EntityTypeBundleValidationTrait;

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected FieldTypePluginManagerInterface $fieldTypePluginManager,
    ) {
    }

    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('entity_type.manager'),
            $container->get('plugin.manager.field.field_type'),
        );
    }


    #[CLI\Hook(type: HookManager::OPTION_HOOK, target: FieldCreateCommands::CREATE)]
    public function hookOption(Command $command, AnnotationData $annotationData): void
    {
        if (!$this->hasAllowedFormats()) {
            return;
        }

        $command->addOption(
            'allowed-formats',
            '',
            InputOption::VALUE_OPTIONAL,
            'Restrict which text formats are allowed, given the user has the required permissions.'
        );
    }

    #[CLI\Hook(type: HookManager::ON_EVENT, target: 'field-create-set-options')]
    public function hookSetOptions(InputInterface $input): void
    {
        if (!$this->hasAllowedFormats($input->getOption('field-type'))) {
            return;
        }

        $input->setOption(
            'allowed-formats',
            $this->input->getOption('allowed-formats') ?? $this->askAllowedFormats()
        );
    }

    #[CLI\Hook(type: HookManager::ON_EVENT, target: 'field-create-field-config')]
    public function hookFieldConfig(array $values, InputInterface $input): array
    {
        if (!$this->hasAllowedFormats($values['field_type'])) {
            return $values;
        }

        $allowedFormats = $this->input->getOption('allowed-formats') ?? [];
        $values['settings']['allowed_formats'] = $allowedFormats;

        return $values;
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
        $choices = ['_none' => '- None -'];

        foreach ($formats as $format) {
            $choices[$format->id()] = $format->label();
        }

        $question = (new ChoiceQuestion('Allowed formats', $choices, '_none'))
            ->setMultiselect(true);

        return array_filter(
            $this->io()->askQuestion($question)
        );
    }
}
