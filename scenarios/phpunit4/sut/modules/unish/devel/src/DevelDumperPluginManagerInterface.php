<?php

namespace Drupal\devel;

use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Interface DevelDumperPluginManagerInterface.
 */
interface DevelDumperPluginManagerInterface extends PluginManagerInterface, FallbackPluginManagerInterface {

  /**
   * Checks if plugin has a definition and is supported.
   *
   * @param string $plugin_id
   *   The ID of the plugin to check.
   *
   * @return bool
   *   TRUE if the plugin is supported, FALSE otherwise.
   */
  public function isPluginSupported($plugin_id);

}
