<?php

namespace Drush\Drupal\Commands\core;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @property InputInterface $input
 * @property EntityTypeBundleInfoInterface $entityTypeBundleInfo
 * @property EntityTypeManagerInterface $entityTypeManager
 */
trait EntityTypeBundleAskTrait
{
    protected function askEntityType(): ?string
    {
        $entityTypeDefinitions = $this->entityTypeManager->getDefinitions();
        $choices = [];

        foreach ($entityTypeDefinitions as $entityTypeDefinition) {
            $choices[$entityTypeDefinition->id()] = $this->input->getOption('show-machine-names')
                ? $entityTypeDefinition->id()
                : $entityTypeDefinition->getLabel();
        }

        if (!$answer = $this->io()->choice('Entity type', $choices)) {
            throw new \InvalidArgumentException(t('The entityType argument is required.'));
        }

        return $answer;
    }

    protected function askBundle(): ?string
    {
        $entityTypeId = $this->input->getArgument('entityType');
        $entityTypeDefinition = $this->entityTypeManager->getDefinition($entityTypeId);
        $bundleEntityType = $entityTypeDefinition->getBundleEntityType();
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId);
        $choices = [];

        if (empty($bundleInfo)) {
            if ($bundleEntityType) {
                throw new \InvalidArgumentException(
                    t('Entity type with id \':entityType\' does not have any bundles.', [':entityType' => $entityTypeId])
                );
            }

            return null;
        }

        foreach ($bundleInfo as $bundle => $data) {
            $label = $this->input->getOption('show-machine-names') ? $bundle : $data['label'];
            $choices[$bundle] = $label;
        }

        if (!$answer = $this->io()->choice('Bundle', $choices)) {
            throw new \InvalidArgumentException(t('The bundle argument is required.'));
        }

        return $answer;
    }
}
