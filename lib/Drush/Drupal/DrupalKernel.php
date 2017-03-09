<?php
namespace Drush\Drupal;

use Drush\Log\LogLevel;
use Drupal\Core\DrupalKernel as DrupalDrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

class DrupalKernel extends DrupalDrupalKernel {
  /** @var ServiceModifierInterface[] */
  protected $serviceModifiers = [];

  /**
   * @inheritdoc
   */
  public static function createFromRequest(Request $request, $class_loader, $environment, $allow_dumping = TRUE, $app_root = NULL) {
    drush_log(dt("Create from request"), LogLevel::DEBUG);
    $kernel = new static($environment, $class_loader, $allow_dumping);
    static::bootEnvironment();
    $kernel->initializeSettings($request);
    return $kernel;
  }

  /**
   * Add a service modifier to the container builder.
   *
   * The container is not compiled until $kernel->boot(), so there is a chance
   * for clients to add compiler passes et. al. before then.
   */
  public function addServiceModifier(ServiceModifierInterface $serviceModifier) {
    drush_log(dt("add service modifier"), LogLevel::DEBUG);
    $this->serviceModifiers[] = $serviceModifier;
  }

  /**
   * @inheritdoc
   */
  protected function getContainerBuilder() {
    drush_log(dt("get container builder"), LogLevel::DEBUG);
    $container = parent::getContainerBuilder();
    foreach ($this->serviceModifiers as $serviceModifier) {
      $serviceModifier->alter($container);
    }
    return $container;
  }
  /**
   * Initializes the service container.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected function initializeContainer() {
    if (empty($this->moduleList) && !$this->containerNeedsRebuild) {
      $container_definition = $this->getCachedContainerDefinition();
      foreach ($this->serviceModifiers as $serviceModifier) {
        if (!$serviceModifier->check($container_definition)) {
          $this->invalidateContainer();
          break;
        }
      }
    }
    return parent::initializeContainer();
  }
}
