<?php

namespace Drupal\devel_generate;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions of the filter module.
 */
class DevelGeneratePermissions implements ContainerInjectionInterface {

  /**
   * The plugin manager.
   *
   * @var \Drupal\devel_generate\DevelGeneratePluginManager;
   */
  protected $develGeneratePluginManager;

  /**
   * Constructs a new DevelGeneratePermissions instance.
   *
   * @param \Drupal\devel_generate\DevelGeneratePluginManager $develGeneratePluginManager
   *   The plugin manager.
   */
  public function __construct(DevelGeneratePluginManager $develGeneratePluginManager) {
    $this->develGeneratePluginManager = $develGeneratePluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.develgenerate'));
  }

  /*
   * A permissions callback.
   *
   * @see devel_generate.permissions.yml.
   *
   * @return array
   */
  function permissions() {
    $devel_generate_plugins = $this->develGeneratePluginManager->getDefinitions();
    foreach ($devel_generate_plugins as $plugin) {

      $permission = $plugin['permission'];
      $permissions[$permission] = array(
        'title' => t($permission),
      );
    }

//    $permissions = array(
//      'administer devel_generate' => array(
//        'title' => t('Administer devel generate'),
//      ),
//    );
    return $permissions;
  }

}
