<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputOption;

use function count;
use function dt;
use function field_purge_batch;

final class FieldDeleteCommands extends DrushCommands
{
    use AutowireTrait;
    use EntityTypeBundleAskTrait;
    use EntityTypeBundleValidationTrait;

    const DELETE = 'field:delete';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected EntityTypeBundleInfoInterface $entityTypeBundleInfo
    ) {
    }

    /**
     * Delete a field
     *
     * @see \Drupal\field_ui\Form\FieldConfigDeleteForm
     */
    #[CLI\Command(name: self::DELETE, aliases: ['field-delete', 'fd'])]
    #[CLI\Argument(name: 'entityType', description: 'The machine name of the entity type.')]
    #[CLI\Argument(name: 'bundle', description: 'The machine name of the bundle.')]
    #[CLI\Option(name: 'field-name', description: 'The machine name of the field.')]
    #[CLI\Option(name: 'all-bundles', description: 'Whether to delete the field from all bundles.')]
    #[CLI\Option(name: 'show-machine-names', description: 'Show machine names instead of labels in option lists.')]
    #[CLI\Usage(name: 'field:delete', description: 'Delete a field by answering the prompts.')]
    #[CLI\Usage(name: 'field-delete taxonomy_term tag', description: 'Delete a field and fill in the remaining information through prompts.')]
    #[CLI\Usage(name: 'field-delete taxonomy_term tag --field-name=field_tag_label', description: 'Delete a field in a non-interactive way.')]
    #[CLI\Usage(name: 'field-delete taxonomy_term --field-name=field_tag_label --all-bundles', description: 'Delete a field from all bundles.')]
    #[CLI\Complete(method_name_or_callable: 'complete')]
    #[CLI\Version(version: '11.0')]
    public function delete(?string $entityType = null, ?string $bundle = null, array $options = [
        'field-name' => InputOption::VALUE_REQUIRED,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
        'all-bundles' => InputOption::VALUE_OPTIONAL,
    ]): void
    {
        $this->input->setArgument('entityType', $entityType ??= $this->askEntityType());
        $this->validateEntityType($entityType);

        $fieldName = $this->input->getOption('field-name') ?: $this->askExisting($entityType, $bundle);
        $this->input->setOption('field-name', $fieldName);

        if ($fieldName === null) {
            throw new \InvalidArgumentException(dt('The !optionName option is required.', [
                '!optionName' => 'field-name',
            ]));
        }

        /** @var FieldConfig[] $results */
        $results = $this->entityTypeManager
            ->getStorage('field_config')
            ->loadByProperties([
                'field_name' => $fieldName,
                'entity_type' => $entityType,
            ]);

        if ($results === []) {
            throw new \InvalidArgumentException(
                dt("Field with name '!fieldName' does not exist.", [
                    '!fieldName' => $fieldName,
                ])
            );
        }

        if (!$options['all-bundles']) {
            $this->input->setArgument('bundle', $bundle = $bundle ?? $this->askBundle());
            $this->validateBundle($entityType, $bundle);

            /** @var FieldConfig[] $results */
            $results = $this->entityTypeManager
                ->getStorage('field_config')
                ->loadByProperties([
                    'field_name' => $fieldName,
                    'entity_type' => $entityType,
                    'bundle' => $bundle,
                ]);

            if ($results === []) {
                throw new \InvalidArgumentException(
                    dt("Field with name '!fieldName' does not exist on bundle '!bundle'.", [
                        '!fieldName' => $fieldName,
                        '!bundle' => $bundle,
                    ])
                );
            }
        }

        foreach ($results as $result) {
            $this->deleteFieldConfig($result);
        }

        // Fields are purged on cron. However field module prevents disabling modules
        // when field types they provided are used in a field until it is fully
        // purged. In the case that a field has minimal or no content, a single call
        // to field_purge_batch() will remove it from the system. Call this with a
        // low batch limit to avoid administrators having to wait for cron runs when
        // removing fields that meet this criteria.
        field_purge_batch(10);
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->getCompletionType() === CompletionInput::TYPE_ARGUMENT_VALUE) {
            if ($input->getCompletionName() === 'entityType') {
                $suggestions->suggestValues(array_keys($this->getFieldableEntityTypes()));
            }

            if ($input->getCompletionName() === 'bundle') {
                $entityTypeId = $input->getArgument('entityType');
                $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId);

                $suggestions->suggestValues(array_keys($bundleInfo));
            }
        }

        if ($input->getCompletionType() === CompletionInput::TYPE_OPTION_VALUE) {
            if ($input->getCompletionName() === 'field-name') {
                $entityTypeId = $input->getArgument('entityType');

                if ($entityTypeId) {
                    $bundle = $input->getArgument('bundle');
                    $fieldNames = array_map(
                        fn (FieldConfig $fieldConfig) => $fieldConfig->get('field_name'),
                        $this->getFieldConfigs($entityTypeId, $bundle),
                    );

                    $suggestions->suggestValues($fieldNames);
                }
            }
        }
    }

    protected function askExisting(string $entityType, ?string $bundle): ?string
    {
        $fieldConfigs = $this->getFieldConfigs($entityType, $bundle);
        $choices = [];

        foreach ($fieldConfigs as $fieldConfig) {
            $label = $this->input->getOption('show-machine-names')
                ? $fieldConfig->get('field_name')
                : $fieldConfig->get('label');

            $choices[$fieldConfig->get('field_name')] = $label;
        }

        return $this->io()->choice('Choose a field to delete', $choices) ?: null;
    }

    protected function askBundle(): ?string
    {
        $entityTypeId = $this->input->getArgument('entityType');
        $entityTypeDefinition = $this->entityTypeManager->getDefinition($entityTypeId);
        $bundleEntityType = $entityTypeDefinition->getBundleEntityType();
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId);
        $choices = [];

        if ($bundleEntityType && $bundleInfo === []) {
            throw new \InvalidArgumentException(
                dt("Entity type with id '!entityType' does not have any bundles.", ['!entityType' => $entityTypeId])
            );
        }

        if ($fieldName = $this->input->getOption('field-name')) {
            $bundleInfo = array_filter($bundleInfo, function (string $bundle) use ($entityTypeId, $fieldName) {
                return $this->entityTypeManager->getStorage('field_config')->load("$entityTypeId.$bundle.$fieldName");
            }, ARRAY_FILTER_USE_KEY);
        }

        if (!$bundleEntityType && count($bundleInfo) === 1) {
            // eg. User
            return $entityTypeId;
        }

        foreach ($bundleInfo as $bundle => $data) {
            $label = $this->input->getOption('show-machine-names') ? $bundle : $data['label'];
            $choices[$bundle] = $label;
        }

        if (!$answer = $this->io()->choice('Bundle', $choices)) {
            throw new \InvalidArgumentException(dt('The bundle argument is required.'));
        }

        return $answer;
    }

    /**
     * Returns all field configs for the given entity type and bundle.
     *
     * @return FieldConfigInterface[]
     */
    protected function getFieldConfigs(string $entityType, ?string $bundle): array
    {
        /** @var FieldConfigInterface[] $fieldConfigs */
        $fieldConfigs = $this->entityTypeManager
            ->getStorage('field_config')
            ->loadByProperties([
                'entity_type' => $entityType,
            ]);

        if ($fieldConfigs === []) {
            throw new \InvalidArgumentException(
                dt("Entity type '!entityType' has no fields.", [
                    '!entityType' => $entityType,
                ])
            );
        }

        if ($bundle !== null) {
            /** @var FieldConfigInterface[] $fieldConfigs */
            $fieldConfigs = $this->entityTypeManager
                ->getStorage('field_config')
                ->loadByProperties([
                    'entity_type' => $entityType,
                    'bundle' => $bundle,
                ]);

            if ($fieldConfigs === []) {
                throw new \InvalidArgumentException(
                    dt("Bundle '!bundle' has no fields.", [
                        '!bundle' => $bundle,
                    ])
                );
            }
        }

        return $fieldConfigs;
    }

    protected function deleteFieldConfig(FieldConfigInterface $fieldConfig): void
    {
        $fieldStorage = $fieldConfig->getFieldStorageDefinition();
        assert($fieldStorage instanceof FieldStorageConfigInterface);

        $bundles = $this->entityTypeBundleInfo->getBundleInfo($fieldConfig->getTargetEntityTypeId());
        $bundleLabel = $bundles[$fieldConfig->getTargetBundle()]['label'];

        if (!$fieldStorage->isLocked()) {
            $fieldConfig->delete();

            // If there are no bundles left for this field storage, it will be
            // deleted too, notify the user about dependencies.
            if ($fieldStorage->getBundles() === []) {
                $fieldStorage->delete();
            }

            $message = 'The field !field has been deleted from the !type bundle.';
        } else {
            $message = 'There was a problem removing the !field from the !type content type.';
        }

        $this->logger()->success(
            dt($message, ['!field' => $fieldConfig->label(), '!type' => $bundleLabel])
        );
    }
}
