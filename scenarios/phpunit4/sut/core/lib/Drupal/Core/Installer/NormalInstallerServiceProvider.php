<?php

namespace Drupal\Core\Installer;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Service provider for the non early installer environment.
 */
class NormalInstallerServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // Use a performance optimised module extension list.
    $container->getDefinition('extension.list.module')->setClass('Drupal\Core\Installer\InstallerModuleExtensionList');
  }

}
