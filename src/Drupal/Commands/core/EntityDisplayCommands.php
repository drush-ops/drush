<?php

namespace Drush\Drupal\Commands\core;

use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

class EntityDisplayCommands extends DrushCommands
{
    /**
     * Rebuild form and display configurations for entities and bundles.
     *
     * See Drupal Core: \Drupal\field\EntityDisplayRebuilder::rebuildEntityTypeDisplays
     *
     * @command entity:rebuild-displays
     *
     * @param string $entity_type_arg Entity-type.
     * @param string $bundle_arg Bundle.
     *
     * @usage drush entity:rebuild-displays
     *   Rebuild form and display configurations for all bundles in all entity-types.
     * @usage drush entity:rebuild-displays node
     *   Rebuild form and display configurations for all bundles in entity-type node.
     * @usage drush entity:rebuild-displays node article
     *   Rebuild form and display configurations for article bundle in entity-type node.
     * @usage drush entity:rebuild-displays all article
     *   Rebuild form and display configurations for article bundle in all entity-types.
     *
     * @usage drush entity:rebuild-displays all all
     *   Alias for `drush entity:rebuild-displays`.
     * @usage drush entity:rebuild-displays node all
     *   Alias for `drush entity:rebuild-displays node`.
     */
    public function rebuildDisplays(string $entity_type_arg = 'all', string $bundle_arg = 'all')
    {
        $this->output()->writeln(dt('Form and display configurations will be rebuild for the following bundles:'));
        $this->io()->newLine();

        /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info */
        $bundle_info = \Drupal::service('entity_type.bundle.info')->getAllBundleInfo();
        foreach ($bundle_info as $entity_type => $bundles) {
            if ($entity_type !== $entity_type_arg && $entity_type_arg !== 'all') {
                unset($bundle_info[$entity_type]);
                continue;
            }

            foreach ($bundles as $bundle => $bundle_settings) {
                if ($bundle !== $bundle_arg && $bundle_arg !== 'all') {
                    unset($bundle_info[$entity_type][$bundle]);
                    continue;
                }
                $this->output()->writeln("$entity_type:$bundle");
            }
        }

        // Simulation is not supported, because class EntityDisplayRebuilder does not support it.
        // Therefor we cannot get a diff of specific configurations that will be changed until we
        // have finished rebuilding all form and display configurations.
        if ($this->getConfig()->simulate()) {
            throw new \Exception(dt('rebuild-display-config command does not support --simulate option.'));
        }
        if (!$this->io()->confirm(dt('Do you really want to continue?'))) {
            throw new UserAbortException();
        }

        /* @var \Drupal\field\EntityDisplayRebuilder $entity_display_rebuilder */
        $entity_display_rebuilder = \Drupal::classResolver(\Drupal\field\EntityDisplayRebuilder::class);
        foreach ($bundle_info as $entity_type => $bundles) {
            foreach ($bundles as $bundle => $bundle_settings) {
                $entity_display_rebuilder->rebuildEntityTypeDisplays($entity_type, $bundle);
                $this->logger()->notice('Finished rebuilding form and display configurations for: ' . $entity_type . ':' . $bundle);
            }
        }

        // Flush all caches at the end of the operation. This ensures that
        // config-export shows the correct changes.
        drupal_flush_all_caches();

        $this->logger()->success(dt('Finished rebuilding form and display configurations. Run drush config:export if you want to add changes to your version control system.'));
    }
}
