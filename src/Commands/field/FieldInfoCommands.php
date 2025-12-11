<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use Composer\Console\Input\InputArgument;
use Composer\Console\Input\InputOption;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\IoTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::INFO,
    description: 'List all configurable fields of an entity bundle.',
    aliases: ['field-info', 'fi'],
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
final class FieldInfoCommands extends Command
{
    use AutowireTrait;
    use FormatterTrait;
    use IoTrait;
    use EntityTypeBundleAskTrait;
    use EntityTypeBundleValidationTrait;
    use FieldDefinitionRowsOfFieldsTrait;

    const string INFO = 'field:info';

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
        private readonly FormatterManager $formatterManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(name: 'entityType', mode: InputArgument::OPTIONAL, description: 'The machine name of the entity type')
            ->addArgument(name: 'bundle', mode: InputArgument::OPTIONAL, description: 'The machine name of the bundle')
            ->addOption(name: 'show-machine-names', mode: InputOption::VALUE_OPTIONAL, description: 'Show machine names instead of labels in option lists')
            ->addUsage('field:info taxonomy_term tag');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setIo($input, $output);

        $entityType = $this->input->getArgument('entityType') ?? $this->askEntityType();
        $this->input->setArgument('entityType', $entityType);
        $this->validateEntityType($entityType);

        $bundle = $this->input->getArgument('bundle') ?? $this->askBundle();
        $this->input->setArgument('bundle', $bundle);
        $this->validateBundle($entityType, $bundle);

        $fieldDefinitions = $this->entityTypeManager
            ->getStorage('field_config')
            ->loadByProperties([
                'entity_type' => $entityType,
                'bundle' => $bundle,
            ]);

        $data = $this->getRowsOfFieldsByFieldDefinitions($fieldDefinitions);
        $this->writeFormattedOutput($input, $output, $data);

        return Command::SUCCESS;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->getCompletionName() === 'entityType') {
            $suggestions->suggestValues(array_keys($this->getFieldableEntityTypes()));
        }

        if ($input->getCompletionName() === 'bundle') {
            $entityTypeId = $input->getArgument('entityType');
            $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId);

            $suggestions->suggestValues(array_keys($bundleInfo));
        }
    }
}
