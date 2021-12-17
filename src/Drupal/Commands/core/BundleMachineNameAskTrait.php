<?php

namespace Drush\Drupal\Commands\core;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @property InputInterface $input
 * @property EntityTypeManagerInterface $entityTypeManager
 */
trait BundleMachineNameAskTrait
{
    protected function askMachineName(string $entityTypeId): string
    {
        $label = $this->input->getOption('label');
        $suggestion = null;
        $machineName = null;

        if ($label) {
            $suggestion = $this->generateMachineName($label);
        }

        while (!$machineName) {
            $answer = $this->io()->ask('Machine-readable name', $suggestion);

            if (preg_match('/[^a-z0-9_]+/', $answer)) {
                $this->logger()->error('The machine-readable name must contain only lowercase letters, numbers, and underscores.');
                continue;
            }

            if (strlen($answer) > EntityTypeInterface::BUNDLE_MAX_LENGTH) {
                $this->logger()->error('Field name must not be longer than :maxLength characters.', [':maxLength' => EntityTypeInterface::BUNDLE_MAX_LENGTH]);
                continue;
            }

            if ($this->bundleExists($entityTypeId, $answer)) {
                $this->logger()->error('A bundle with this name already exists.');
                continue;
            }

            $machineName = $answer;
        }

        return $machineName;
    }

    protected function generateMachineName(string $source): string
    {
        // Only lowercase alphanumeric characters and underscores
        $machineName = preg_replace('/[^_a-z0-9]/i', '_', $source);
        // Maximum one subsequent underscore
        $machineName = preg_replace('/_+/', '_', $machineName);
        // Only lowercase
        $machineName = strtolower($machineName);
        // Maximum length
        $machineName = substr($machineName, 0, EntityTypeInterface::BUNDLE_MAX_LENGTH);

        return $machineName;
    }

    protected function bundleExists(string $entityTypeId, string $id): bool
    {
        if ($entityTypeDefinition = $this->entityTypeManager->getDefinition($entityTypeId)) {
            if ($bundleEntityType = $entityTypeDefinition->getBundleEntityType()) {
                $bundleDefinition = $this->entityTypeManager
                    ->getStorage($bundleEntityType)
                    ->load($id);
            }
        }

        return isset($bundleDefinition);
    }
}
