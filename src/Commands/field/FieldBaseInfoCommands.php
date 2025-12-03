<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::BASE_INFO,
    description: 'List all base fields of an entity type.',
    aliases: ['field-base-info', 'fbi'],
)]
#[CLI\DefaultTableFields(fields: [
    'field_name',
    'required',
    'field_type',
    'cardinality',
])]
#[CLI\FieldLabels(labels: [
    'label' => 'Label',
    'description' => 'Description',
    'field_name' => 'Field name',
    'field_type' => 'Field type',
    'required' => 'Required',
    'translatable' => 'Translatable',
    'cardinality' => 'Cardinality',
    'default_value' => 'Default value',
    'default_value_callback' => 'Default value callback',
    'allowed_values' => 'Allowed values',
    'allowed_values_function' => 'Allowed values function',
    'handler' => 'Selection handler',
    'target_bundles' => 'Target bundles',
])]
#[CLI\FilterDefaultField(field: 'field_name')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
#[CLI\Version(version: '11.0')]
final class FieldBaseInfoCommands extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    use EntityTypeBundleAskTrait;
    use EntityTypeBundleValidationTrait;
    use FieldDefinitionRowsOfFieldsTrait;

    const string BASE_INFO = 'field:base-info';

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
        private readonly EntityFieldManagerInterface $entityFieldManager,
        private readonly FormatterManager $formatterManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entityType', InputArgument::REQUIRED, 'The machine name of the entity type')
            ->addOption(name: 'show-machine-names', description: 'Show machine names instead of labels in option lists.')
            ->addUsage('field:base-info taxonomy_term');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $entityType = $input->getArgument('entityType') ?? $this->askEntityType();
        $input->setArgument('entityType', $entityType);
        $this->validateEntityType($entityType);

        $fieldDefinitions = $this->entityFieldManager->getBaseFieldDefinitions($entityType);

        $data = $this->getRowsOfFieldsByFieldDefinitions($fieldDefinitions);
        $this->writeFormattedOutput($input, $output, $data);

        return Command::SUCCESS;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->getCompletionName() === 'entityType') {
            $suggestions->suggestValues(array_keys($this->getFieldableEntityTypes()));
        }
    }
}
