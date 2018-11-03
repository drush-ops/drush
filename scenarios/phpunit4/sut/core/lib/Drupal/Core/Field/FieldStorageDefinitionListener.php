<?php

namespace Drupal\Core\Field;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityStorageInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Reacts to field storage definition CRUD on behalf of the Entity system.
 *
 * @see \Drupal\Core\Field\FieldStorageDefinitionEvents
 */
class FieldStorageDefinitionListener implements FieldStorageDefinitionListenerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The entity definition manager.
   *
   * @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface
   */
  protected $entityLastInstalledSchemaRepository;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The deleted fields repository.
   *
   * @var \Drupal\Core\Field\DeletedFieldsRepositoryInterface
   */
  protected $deletedFieldsRepository;

  /**
   * Constructs a new FieldStorageDefinitionListener.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $entity_last_installed_schema_repository
   *   The entity last installed schema repository.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Field\DeletedFieldsRepositoryInterface $deleted_fields_repository
   *   The deleted fields repository.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, EntityLastInstalledSchemaRepositoryInterface $entity_last_installed_schema_repository, EntityFieldManagerInterface $entity_field_manager, DeletedFieldsRepositoryInterface $deleted_fields_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->entityLastInstalledSchemaRepository = $entity_last_installed_schema_repository;
    $this->entityFieldManager = $entity_field_manager;
    $this->deletedFieldsRepository = $deleted_fields_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldStorageDefinitionCreate(FieldStorageDefinitionInterface $storage_definition) {
    $entity_type_id = $storage_definition->getTargetEntityTypeId();

    // @todo Forward this to all interested handlers, not only storage, once
    //   iterating handlers is possible: https://www.drupal.org/node/2332857.
    $storage = clone $this->entityTypeManager->getStorage($entity_type_id);

    // Entity type definition updates can change the schema by adding or
    // removing entity tables (for example when switching an entity type from
    // non-revisionable to revisionable), so CRUD operations on a field storage
    // definition need to use the last installed entity type schema.
    if ($storage instanceof SqlContentEntityStorage
       && ($last_installed_entity_type = $this->entityLastInstalledSchemaRepository->getLastInstalledDefinition($entity_type_id))) {
      $storage->setEntityType($last_installed_entity_type);
    }

    if ($storage instanceof FieldStorageDefinitionListenerInterface) {
      $storage->onFieldStorageDefinitionCreate($storage_definition);
    }

    $this->eventDispatcher->dispatch(FieldStorageDefinitionEvents::CREATE, new FieldStorageDefinitionEvent($storage_definition));

    $this->entityLastInstalledSchemaRepository->setLastInstalledFieldStorageDefinition($storage_definition);
    $this->entityFieldManager->clearCachedFieldDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldStorageDefinitionUpdate(FieldStorageDefinitionInterface $storage_definition, FieldStorageDefinitionInterface $original) {
    $entity_type_id = $storage_definition->getTargetEntityTypeId();

    // @todo Forward this to all interested handlers, not only storage, once
    //   iterating handlers is possible: https://www.drupal.org/node/2332857.
    $storage = clone $this->entityTypeManager->getStorage($entity_type_id);

    // Entity type definition updates can change the schema by adding or
    // removing entity tables (for example when switching an entity type from
    // non-revisionable to revisionable), so CRUD operations on a field storage
    // definition need to use the last installed entity type schema.
    if ($storage instanceof SqlContentEntityStorage
       && ($last_installed_entity_type = $this->entityLastInstalledSchemaRepository->getLastInstalledDefinition($entity_type_id))) {
      $storage->setEntityType($last_installed_entity_type);
    }

    if ($storage instanceof FieldStorageDefinitionListenerInterface) {
      $storage->onFieldStorageDefinitionUpdate($storage_definition, $original);
    }

    $this->eventDispatcher->dispatch(FieldStorageDefinitionEvents::UPDATE, new FieldStorageDefinitionEvent($storage_definition, $original));

    $this->entityLastInstalledSchemaRepository->setLastInstalledFieldStorageDefinition($storage_definition);
    $this->entityFieldManager->clearCachedFieldDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldStorageDefinitionDelete(FieldStorageDefinitionInterface $storage_definition) {
    $entity_type_id = $storage_definition->getTargetEntityTypeId();

    // @todo Forward this to all interested handlers, not only storage, once
    //   iterating handlers is possible: https://www.drupal.org/node/2332857.
    $storage = clone $this->entityTypeManager->getStorage($entity_type_id);

    // Entity type definition updates can change the schema by adding or
    // removing entity tables (for example when switching an entity type from
    // non-revisionable to revisionable), so CRUD operations on a field storage
    // definition need to use the last installed entity type schema.
    if ($storage instanceof SqlContentEntityStorage
       && ($last_installed_entity_type = $this->entityLastInstalledSchemaRepository->getLastInstalledDefinition($entity_type_id))) {
      $storage->setEntityType($last_installed_entity_type);
    }

    // Keep the field definition in the deleted fields repository so we can use
    // it later during field_purge_batch(), but only if the field has data.
    try {
      if ($storage_definition instanceof BaseFieldDefinition && $storage instanceof FieldableEntityStorageInterface && $storage->countFieldData($storage_definition, TRUE)) {
        $deleted_storage_definition = clone $storage_definition;
        $deleted_storage_definition->setDeleted(TRUE);
        $this->deletedFieldsRepository->addFieldDefinition($deleted_storage_definition);
        $this->deletedFieldsRepository->addFieldStorageDefinition($deleted_storage_definition);
      }
    }
    catch (DatabaseExceptionWrapper $e) {
      // This may happen when changing field storage schema, since we are not
      // able to use a table mapping matching the passed storage definition.
      // @todo Revisit this once we are able to instantiate the table mapping
      //   properly. See https://www.drupal.org/node/2274017.
    }

    if ($storage instanceof FieldStorageDefinitionListenerInterface) {
      $storage->onFieldStorageDefinitionDelete($storage_definition);
    }

    $this->eventDispatcher->dispatch(FieldStorageDefinitionEvents::DELETE, new FieldStorageDefinitionEvent($storage_definition));

    $this->entityLastInstalledSchemaRepository->deleteLastInstalledFieldStorageDefinition($storage_definition);
    $this->entityFieldManager->clearCachedFieldDefinitions();
  }

}
