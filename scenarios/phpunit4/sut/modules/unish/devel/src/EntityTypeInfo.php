<?php

namespace Drupal\devel;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manipulates entity type information.
 *
 * This class contains primarily bridged hooks for compile-time or
 * cache-clear-time hooks. Runtime hooks should be placed in EntityOperations.
 */
class EntityTypeInfo implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * EntityTypeInfo constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * Adds devel links to appropriate entity types.
   *
   * This is an alter hook bridge.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface[] $entity_types
   *   The master entity type list to alter.
   *
   * @see hook_entity_type_alter()
   */
  public function entityTypeAlter(array &$entity_types) {
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (($entity_type->getFormClass('default') || $entity_type->getFormClass('edit')) && $entity_type->hasLinkTemplate('edit-form')) {
        $entity_type->setLinkTemplate('devel-load', "/devel/$entity_type_id/{{$entity_type_id}}");
      }
      if ($entity_type->hasViewBuilderClass() && $entity_type->hasLinkTemplate('canonical')) {
        $entity_type->setLinkTemplate('devel-render', "/devel/$entity_type_id/{{$entity_type_id}}/render");
      }
      if ($entity_type->hasLinkTemplate('devel-render') || $entity_type->hasLinkTemplate('devel-load')) {
        $entity_type->setLinkTemplate('devel-definition', "/devel/$entity_type_id/{{$entity_type_id}}/definition");
      }
    }
  }

  /**
   * Adds devel operations on entity that supports it.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity on which to define an operation.
   *
   * @return array
   *   An array of operation definitions.
   *
   * @see hook_entity_operation()
   */
  public function entityOperation(EntityInterface $entity) {
    $operations = [];
    if ($this->currentUser->hasPermission('access devel information')) {
      if ($entity->hasLinkTemplate('devel-load')) {
        $operations['devel'] = [
          'title' => $this->t('Devel'),
          'weight' => 100,
          'url' => $entity->toUrl('devel-load'),
        ];
      }
      elseif ($entity->hasLinkTemplate('devel-render')) {
        $operations['devel'] = [
          'title' => $this->t('Devel'),
          'weight' => 100,
          'url' => $entity->toUrl('devel-render'),
        ];
      }
    }
    return $operations;
  }

}
