<?php

namespace Drupal\migrate\Plugin\Exception;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;

/**
 * Defines a class for bad plugin definition exceptions.
 */
class BadPluginDefinitionException extends InvalidPluginDefinitionException {

  /**
   * Constructs a BadPluginDefinitionException.
   *
   * For the remaining parameters see \Exception.
   *
   * @param string $plugin_id
   *   The plugin ID of the mapper.
   * @param string $property
   *   The name of the property that is missing from the plugin.
   *
   * @see \Exception
   */
  public function __construct($plugin_id, $property, $code = 0, \Exception $previous = NULL) {
    $message = sprintf('The %s plugin must define the %s property.', $plugin_id, $property);
    parent::__construct($plugin_id, $message, $code, $previous);
  }

}
