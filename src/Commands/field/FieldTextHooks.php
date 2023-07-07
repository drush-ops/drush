<?php

namespace Drush\Commands\field;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
    ) {
    }

    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('entity_type.manager'),
        );
    }


    #[CLI\Hook(type: HookManager::OPTION_HOOK, target: FieldCreateCommands::CREATE)]
    public function hookOption(Command $command, AnnotationData $annotationData): void
    {
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
        if (!in_array($input->getOption('field-type'), _allowed_formats_field_types(), true)) {
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
        if (!in_array($values['field_type'], ['text', 'text_long', 'text_with_summary'], true)) {
            return $values;
        }

        $allowedFormats = $this->input->getOption('allowed-formats') ?? [];
        $values['settings']['allowed_formats'] = $allowedFormats;

        return $values;
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
