<?php

namespace Drupal\Core\Entity;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a base class for entity handlers.
 *
 * @deprecated in Drupal 8.0.x, will be removed before Drupal 9.0.0.
 *   Implement the container injection pattern of
 *   \Drupal\Core\Entity\EntityHandlerInterface::createInstance() to obtain the
 *   module handler service for your class.
 *
 * @ingroup entity_api
 */
abstract class EntityHandlerBase {
  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The module handler to invoke hooks on.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Gets the module handler.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler.
   */
  protected function moduleHandler() {
    if (!$this->moduleHandler) {
      $this->moduleHandler = \Drupal::moduleHandler();
    }
    return $this->moduleHandler;
  }

  /**
   * Sets the module handler for this handler.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   *
   * @return $this
   */
  public function setModuleHandler(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
    return $this;
  }

}
