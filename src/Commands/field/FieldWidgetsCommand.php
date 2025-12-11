<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\IoTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: self::NAME,
    description: 'Lists field widgets.',
)]
#[CLI\DefaultFields(fields: [
    'id',
    'label',
    'field_types',
])]
#[CLI\FieldLabels(labels: [
    'id' => 'ID',
    'label' => 'Label',
    'default_settings' => 'Default Settings',
    'field_types' => 'Field types',
    'settings' => 'Settings',
    'class' => 'Class',
    'provider' => 'Provider',
])]
#[CLI\FilterDefaultField(field: 'id')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class FieldWidgetsCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;
    use IoTrait;

    const string NAME = 'field:widgets';

    public function __construct(
        private readonly FieldTypePluginManagerInterface $typePluginManager,
        // @todo These attributes should not be needed but services aren't found otherwise.
        #[Autowire(service: 'plugin.manager.field.widget')]
        private readonly WidgetPluginManager $widgetPluginManager,
        private readonly FormatterManager $formatterManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(name: 'field-type', mode: InputOption::VALUE_REQUIRED, description: 'Applicable field type')
            ->addUsage('field:widgets --field-type=entity_reference');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setIo($input, $output);

        $processor = static fn (array $definition): array => [
            'id' => $definition['id'],
            'label' => (string) $definition['label'],
            'default_settings' => $definition['class']::defaultSettings(),
            'field_types' => $definition['field_types'],
        ];

        $definitions = \array_map($processor, $this->widgetPluginManager->getDefinitions());
        $definitions = \array_values($definitions);

        if ($fieldType = $input->getOption('field-type')) {
            $definitions = $this->filterByFieldType($definitions, $fieldType);
        }

        $result = new RowsOfFields($definitions);
        $result->addRendererFunction([$this, 'renderArray']);
        $this->writeFormattedOutput($input, $output, $result);

        return Command::SUCCESS;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->getCompletionType() === CompletionInput::TYPE_OPTION_VALUE) {
            if ($input->getCompletionName() === 'field-type') {
                $fieldTypes = $this->typePluginManager->getDefinitions();
                $suggestions->suggestValues(array_keys($fieldTypes));
            }
        }
    }
    
    public function renderArray($key, $value, FormatterOptions $options)
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return implode("\n", $value);
            }

            return Yaml::dump($value);
        }

        return $value;
    }

    /**
     * Filters definitions by applicable field types.
     */
    private function filterByFieldType(array $definitions, string $search): array
    {
        $match = static fn (string $field_type): bool => \str_contains($field_type, $search);
        $totalMatches = static fn (array $field_types): int => \count(\array_filter($field_types, $match));
        $hasMatches = static fn (array $definition): bool => $totalMatches($definition['field_types']) > 0;

        return \array_filter($definitions, $hasMatches);
    }
}
