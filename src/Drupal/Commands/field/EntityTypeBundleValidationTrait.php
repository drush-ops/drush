<?php

namespace Drush\Drupal\Commands\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;

use function t;

/**
 * @property EntityTypeManagerInterface $entityTypeManager
 */
trait EntityTypeBundleValidationTrait
{
    protected function validateEntityType(string $entityTypeId): void
    {
        if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
            throw new \InvalidArgumentException(
                t("Entity type with id ':entityType' does not exist.", [':entityType' => $entityTypeId])
            );
        }
    }

    protected function validateBundle(string $entityTypeId, string $bundle): void
    {
        if (!$entityTypeDefinition = $this->entityTypeManager->getDefinition($entityTypeId)) {
            return;
        }

        $bundleEntityType = $entityTypeDefinition->getBundleEntityType();

        if ($bundleEntityType === null && $bundle === $entityTypeId) {
            return;
        }

        $bundleDefinition = $this->entityTypeManager
            ->getStorage($bundleEntityType)
            ->load($bundle);

        if (!$bundleDefinition) {
            throw new \InvalidArgumentException(
                t("Bundle ':bundle' does not exist on entity type with id ':entityType'.", [
                    ':bundle' => $bundle,
                    ':entityType' => $entityTypeId,
                ])
            );
        }
    }
}
