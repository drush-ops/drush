<?php

namespace Drush\Drupal\Commands\field;

use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputOption;

use function count;
use function dt;
use function field_purge_batch;
use function t;

class FieldDeleteCommands extends DrushCommands
{
    use EntityTypeBundleAskTrait;
    use EntityTypeBundleValidationTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfo $entityTypeBundleInfo
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    }

    /**
     * Delete a field
     *
     * @command field:delete
     * @aliases field-delete,fd
     *
     * @param string $entityType
     *      The machine name of the entity type
     * @param string $bundle
     *      The machine name of the bundle
     *
     * @option field-name
     *      The machine name of the field
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @usage drush field:delete
     *      Delete a field by answering the prompts.
     * @usage drush field-delete taxonomy_term tag
     *      Delete a field and fill in the remaining information through prompts.
     * @usage drush field-delete taxonomy_term tag --field-name=field_tag_label
     *      Delete a field in a non-interactive way.
     *
     * @version 11.0
     * @see \Drupal\field_ui\Form\FieldConfigDeleteForm
     */
    public function delete(?string $entityType = null, ?string $bundle = null, array $options = [
        'field-name' => InputOption::VALUE_REQUIRED,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ]): void
    {
        $this->input->setArgument('entityType', $entityType = $entityType ?? $this->askEntityType());
        $this->validateEntityType($entityType);

        $this->input->setArgument('bundle', $bundle = $bundle ?? $this->askBundle());
        $this->validateBundle($entityType, $bundle);

        $fieldName = $this->input->getOption('field-name') ?? $this->askExisting($entityType, $bundle);
        $this->input->setOption('field-name', $fieldName);

        if ($fieldName === '') {
            throw new \InvalidArgumentException(dt('The %optionName option is required.', [
                '%optionName' => 'field-name',
            ]));
        }

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
                t("Field with name ':fieldName' does not exist on bundle ':bundle'.", [
                    ':fieldName' => $fieldName,
                    ':bundle' => $bundle,
                ])
            );
        }

        $this->deleteFieldConfig(reset($results));

        // Fields are purged on cron. However field module prevents disabling modules
        // when field types they provided are used in a field until it is fully
        // purged. In the case that a field has minimal or no content, a single call
        // to field_purge_batch() will remove it from the system. Call this with a
        // low batch limit to avoid administrators having to wait for cron runs when
        // removing fields that meet this criteria.
        field_purge_batch(10);
    }

    protected function askExisting(string $entityType, string $bundle): string
    {
        $choices = [];
        /** @var FieldConfigInterface[] $fieldConfigs */
        $fieldConfigs = $this->entityTypeManager
            ->getStorage('field_config')
            ->loadByProperties([
                'entity_type' => $entityType,
                'bundle' => $bundle,
            ]);

        foreach ($fieldConfigs as $fieldConfig) {
            $label = $this->input->getOption('show-machine-names')
                ? $fieldConfig->get('field_name')
                : $fieldConfig->get('label');

            $choices[$fieldConfig->get('field_name')] = $label;
        }

        if ($choices === []) {
            throw new \InvalidArgumentException(
                t("Bundle ':bundle' has no fields.", [
                    ':bundle' => $bundle,
                ])
            );
        }

        return $this->io()->choice('Choose a field to delete', $choices);
    }

    protected function deleteFieldConfig(FieldConfigInterface $fieldConfig): void
    {
        $fieldStorage = $fieldConfig->getFieldStorageDefinition();
        $bundles = $this->entityTypeBundleInfo->getBundleInfo($fieldConfig->getTargetEntityTypeId());
        $bundleLabel = $bundles[$fieldConfig->getTargetBundle()]['label'];

        if ($fieldStorage && !$fieldStorage->isLocked()) {
            $fieldConfig->delete();

            // If there are no bundles left for this field storage, it will be
            // deleted too, notify the user about dependencies.
            if ($fieldStorage->getBundles() === []) {
                $fieldStorage->delete();
            }

            $message = 'The field :field has been deleted from the :type bundle.';
        } else {
            $message = 'There was a problem removing the :field from the :type content type.';
        }

        $this->logger()->success(
            t($message, [':field' => $fieldConfig->label(), ':type' => $bundleLabel])
        );
    }
}
