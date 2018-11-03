<?php

namespace Drupal\Core\Plugin;

/**
 * Provides a trait with typical behavior for plugins which have forms.
 */
trait PluginWithFormsTrait {

  /**
   * {@inheritdoc}
   */
  public function getFormClass($operation) {
    if (isset($this->getPluginDefinition()['forms'][$operation])) {
      return $this->getPluginDefinition()['forms'][$operation];
    }
    elseif ($operation === 'configure' && $this instanceof PluginFormInterface) {
      return static::class;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasFormClass($operation) {
    return !empty($this->getFormClass($operation));
  }

}
