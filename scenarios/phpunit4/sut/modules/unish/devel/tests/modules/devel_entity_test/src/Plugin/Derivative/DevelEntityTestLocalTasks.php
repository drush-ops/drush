<?php

namespace Drupal\devel_entity_test\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Defines the local tasks for all the entity_test entities.
 */
class DevelEntityTestLocalTasks extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = array();

    $this->derivatives['devel_entity_test_canonical.canonical'] = array();
    $this->derivatives['devel_entity_test_canonical.canonical']['base_route'] = "entity.devel_entity_test_canonical.canonical";
    $this->derivatives['devel_entity_test_canonical.canonical']['route_name'] = "entity.devel_entity_test_canonical.canonical";
    $this->derivatives['devel_entity_test_canonical.canonical']['title'] = 'View';

    $this->derivatives['devel_entity_test_edit.edit'] = array();
    $this->derivatives['devel_entity_test_edit.edit']['base_route'] = "entity.devel_entity_test_edit.edit_form";
    $this->derivatives['devel_entity_test_edit.edit']['route_name'] = "entity.devel_entity_test_edit.edit_form";
    $this->derivatives['devel_entity_test_edit.edit']['title'] = 'Edit';

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
