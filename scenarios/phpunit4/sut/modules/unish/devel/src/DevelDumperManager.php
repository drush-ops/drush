<?php

namespace Drupal\devel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Class DevelDumperManager
 */
class DevelDumperManager implements DevelDumperManagerInterface {

  /**
   * The devel config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The devel dumper plugin manager.
   *
   * @var \Drupal\devel\DevelDumperPluginManagerInterface
   */
  protected $dumperManager;

  /**
   * Constructs a DevelDumperPluginManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current account.
   * @param \Drupal\devel\DevelDumperPluginManagerInterface $dumper_manager
   *   The devel dumper plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountProxyInterface $account, DevelDumperPluginManagerInterface $dumper_manager) {
    $this->config = $config_factory->get('devel.settings');
    $this->account = $account;
    $this->dumperManager = $dumper_manager;
  }

  /**
   * Instances a new dumper plugin.
   *
   * @param string $plugin_id
   *   (optional) The plugin ID, defaults to NULL.
   *
   * @return \Drupal\devel\DevelDumperInterface
   *   Returns the devel dumper plugin instance.
   */
  protected function createInstance($plugin_id = NULL) {
    if (!$plugin_id || !$this->dumperManager->isPluginSupported($plugin_id)) {
      $plugin_id = $this->config->get('devel_dumper');
    }
    return $this->dumperManager->createInstance($plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function dump($input, $name = NULL, $plugin_id = NULL) {
    if ($this->hasAccessToDevelInformation()) {
      $this->createInstance($plugin_id)->dump($input, $name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function export($input, $name = NULL, $plugin_id = NULL) {
    if ($this->hasAccessToDevelInformation()) {
      return $this->createInstance($plugin_id)->export($input, $name);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function message($input, $name = NULL, $type = 'status', $plugin_id = NULL) {
    if ($this->hasAccessToDevelInformation()) {
      $output = $this->export($input, $name, $plugin_id);
      drupal_set_message($output, $type, TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function debug($input, $name = NULL, $plugin_id = NULL) {
    $output = $this->createInstance($plugin_id)->export($input, $name) . "\n";
    // The temp directory does vary across multiple simpletest instances.
    $file = file_directory_temp() . '/drupal_debug.txt';
    if (file_put_contents($file, $output, FILE_APPEND) === FALSE && $this->hasAccessToDevelInformation()) {
      drupal_set_message(t('Devel was unable to write to %file.', ['%file' => $file]), 'error');
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function dumpOrExport($input, $name = NULL, $export = TRUE, $plugin_id = NULL) {
    if ($this->hasAccessToDevelInformation()) {
      $dumper = $this->createInstance($plugin_id);
      if ($export) {
        return $dumper->export($input, $name);
      }
      $dumper->dump($input, $name);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function exportAsRenderable($input, $name = NULL, $plugin_id = NULL) {
    if ($this->hasAccessToDevelInformation()) {
      return $this->createInstance($plugin_id)->exportAsRenderable($input, $name);
    }
    return [];
  }

  /**
   * Checks whether a user has access to devel information.
   *
   * @return bool
   *   TRUE if the user has the permission, FALSE otherwise.
   */
  protected function hasAccessToDevelInformation() {
    return $this->account && $this->account->hasPermission('access devel information');
  }

}
