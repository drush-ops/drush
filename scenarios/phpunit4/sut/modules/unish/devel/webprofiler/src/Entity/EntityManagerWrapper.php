<?php

namespace Drupal\webprofiler\Entity;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\webprofiler\Entity\Decorators\Config\ConfigEntityStorageDecorator;
use Drupal\webprofiler\Entity\Decorators\Config\RoleStorageDecorator;
use Drupal\webprofiler\Entity\Decorators\Config\ShortcutSetStorageDecorator;
use Drupal\webprofiler\Entity\Decorators\Config\VocabularyStorageDecorator;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EntityManagerWrapper
 */
class EntityManagerWrapper extends DefaultPluginManager implements EntityTypeManagerInterface, ContainerAwareInterface {

  /**
   * @var array
   */
  private $loaded;

  /**
   * @var array
   */
  private $rendered;

  /**
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  private $entityManager;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   */
  public function __construct(EntityTypeManagerInterface $entityManager) {
    $this->entityManager = $entityManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage($entity_type) {
    /** @var ConfigEntityStorageInterface $handler */
    $handler = $this->getHandler($entity_type, 'storage');
    $type = ($handler instanceof ConfigEntityStorageInterface) ? 'config' : 'content';

    if (!isset($this->loaded[$type][$entity_type])) {
      $handler = $this->getStorageDecorator($entity_type, $handler);
      $this->loaded[$type][$entity_type] = $handler;
    }
    else {
      $handler = $this->loaded[$type][$entity_type];
    }

    return $handler;
  }

  /**
   * {@inheritdoc}
   */
  public function getViewBuilder($entity_type) {
    /** @var EntityViewBuilderInterface $handler */
    $handler = $this->getHandler($entity_type, 'view_builder');

    if ($handler instanceof EntityViewBuilderInterface) {
      if (!isset($this->rendered[$entity_type])) {
        $handler = new EntityViewBuilderDecorator($handler);
        $this->rendered[$entity_type] = $handler;
      }
      else {
        $handler = $this->rendered[$entity_type];
      }
    }

    return $handler;
  }

  /**
   * @param $entity_type
   * @param $handler
   *
   * @return \Drupal\webprofiler\Entity\EntityDecorator
   */
  private function getStorageDecorator($entity_type, $handler) {
    if ($handler instanceof ConfigEntityStorageInterface) {
      switch ($entity_type) {
        case 'taxonomy_vocabulary':
          return new VocabularyStorageDecorator($handler);
          break;
        case 'user_role':
          return new RoleStorageDecorator($handler);
          break;
        case 'shortcut_set':
          return new ShortcutSetStorageDecorator($handler);
          break;
        default:
          return new ConfigEntityStorageDecorator($handler);
          break;
      }
    }
    return $handler;
  }

  /**
   * @param $type
   * @param $entity_type
   *
   * @return array
   */
  public function getLoaded($type, $entity_type) {
    return isset($this->loaded[$type][$entity_type]) ? $this->loaded[$type][$entity_type] : NULL;
  }

  /**
   * @param $entity_type
   *
   * @return array
   */
  public function getRendered( $entity_type) {
    return isset($this->rendered[$entity_type]) ? $this->rendered[$entity_type] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function useCaches($use_caches = FALSE) {
    $this->entityManager->useCaches($use_caches);
  }

  /**
   * {@inheritdoc}
   */
  public function hasDefinition($plugin_id) {
    return $this->entityManager->hasDefinition($plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessControlHandler($entity_type) {
    return $this->entityManager->getAccessControlHandler($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions() {
    $this->entityManager->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function getListBuilder($entity_type) {
    return $this->entityManager->getListBuilder($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormObject($entity_type, $operation) {
    return $this->entityManager->getFormObject($entity_type, $operation);
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteProviders($entity_type) {
    return $this->entityManager->getRouteProviders($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function hasHandler($entity_type, $handler_type) {
    return $this->entityManager->hasHandler($entity_type, $handler_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getHandler($entity_type, $handler_type) {
    return $this->entityManager->getHandler($entity_type, $handler_type);
  }

  /**
   * {@inheritdoc}
   */
  public function createHandlerInstance(
    $class,
    EntityTypeInterface $definition = NULL
  ) {
    return $this->entityManager->createHandlerInstance($class, $definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($entity_type_id, $exception_on_invalid = TRUE) {
    return $this->entityManager->getDefinition(
      $entity_type_id,
      $exception_on_invalid
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    return $this->entityManager->getDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    return $this->entityManager->createInstance($plugin_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options) {
    return $this->entityManager->getInstance($options);
  }

  /**
   * {@inheritdoc}
   */
  public function setContainer(ContainerInterface $container = NULL) {
    $this->entityManager->setContainer($container);
  }

}
