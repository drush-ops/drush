<?php

namespace Drupal\Core\Menu;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Defines an implementation of the menu link override using a config file.
 */
class StaticMenuLinkOverrides implements StaticMenuLinkOverridesInterface {

  /**
   * The config name used to store the overrides.
   *
   * This configuration can not be overridden by configuration overrides because
   * menu links and these overrides are cached in a way that is not override
   * aware.
   *
   * @var string
   */
  protected $configName = 'core.menu.static_menu_link_overrides';

  /**
   * The menu link overrides config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The config factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a StaticMenuLinkOverrides object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A configuration factory instance.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Gets the configuration object when needed.
   *
   * Since this service is injected into all static menu link objects, but
   * only used when updating one, avoid actually loading the config when it's
   * not needed.
   */
  protected function getConfig() {
    if (empty($this->config)) {
      // Get an override free and editable configuration object.
      $this->config = $this->configFactory->getEditable($this->configName);
    }
    return $this->config;
  }

  /**
   * {@inheritdoc}
   */
  public function reload() {
    $this->config = NULL;
    $this->configFactory->reset($this->configName);
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverride($id) {
    $all_overrides = $this->getConfig()->get('definitions');
    $id = static::encodeId($id);
    return $id && isset($all_overrides[$id]) ? $all_overrides[$id] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultipleOverrides(array $ids) {
    $all_overrides = $this->getConfig()->get('definitions');
    $save = FALSE;
    foreach ($ids as $id) {
      $id = static::encodeId($id);
      if (isset($all_overrides[$id])) {
        unset($all_overrides[$id]);
        $save = TRUE;
      }
    }
    if ($save) {
      $this->getConfig()->set('definitions', $all_overrides)->save();
    }
    return $save;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteOverride($id) {
    return $this->deleteMultipleOverrides([$id]);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultipleOverrides(array $ids) {
    $result = [];
    if ($ids) {
      $all_overrides = $this->getConfig()->get('definitions') ?: [];
      foreach ($ids as $id) {
        $encoded_id = static::encodeId($id);
        if (isset($all_overrides[$encoded_id])) {
          $result[$id] = $all_overrides[$encoded_id];
        }
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function saveOverride($id, array $definition) {
    // Only allow to override a specific subset of the keys.
    $expected = [
      'menu_name' => '',
      'parent' => '',
      'weight' => 0,
      'expanded' => FALSE,
      'enabled' => FALSE,
    ];
    // Filter the overrides to only those that are expected.
    $definition = array_intersect_key($definition, $expected);
    // Ensure all values are set.
    $definition = $definition + $expected;
    if ($definition) {
      // Cast keys to avoid config schema during save.
      $definition['menu_name'] = (string) $definition['menu_name'];
      $definition['parent'] = (string) $definition['parent'];
      $definition['weight'] = (int) $definition['weight'];
      $definition['expanded'] = (bool) $definition['expanded'];
      $definition['enabled'] = (bool) $definition['enabled'];

      $id = static::encodeId($id);
      $all_overrides = $this->getConfig()->get('definitions');
      // Combine with any existing data.
      $all_overrides[$id] = $definition + $this->loadOverride($id);
      $this->getConfig()->set('definitions', $all_overrides)->save(TRUE);
    }
    return array_keys($definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->getConfig()->getCacheTags();
  }

  /**
   * Encodes the ID by replacing dots with double underscores.
   *
   * This is done because config schema uses dots for its internal type
   * hierarchy. Double underscores are converted to triple underscores to
   * avoid accidental conflicts.
   *
   * @param string $id
   *   The menu plugin ID.
   *
   * @return string
   *   The menu plugin ID with double underscore instead of dots.
   */
  protected static function encodeId($id) {
    return strtr($id, ['.' => '__', '__' => '___']);
  }

}
