<?php

namespace Drupal\Core\Config\Entity;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\EntityManagerInterface;

/**
 * Lists affected configuration entities by a dependency removal.
 *
 * This trait relies on the StringTranslationTrait.
 */
trait ConfigDependencyDeleteFormTrait {

  /**
   * Translates a string to the current language or to a given language.
   *
   * Provided by \Drupal\Core\StringTranslation\StringTranslationTrait.
   */
  abstract protected function t($string, array $args = [], array $options = []);

  /**
   * Adds form elements to list affected configuration entities.
   *
   * @param array $form
   *   The form array to add elements to.
   * @param string $type
   *   The type of dependency being checked. Either 'module', 'theme', 'config'
   *   or 'content'.
   * @param array $names
   *   The specific names to check. If $type equals 'module' or 'theme' then it
   *   should be a list of module names or theme names. In the case of 'config'
   *   or 'content' it should be a list of configuration dependency names.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The config manager.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   *
   * @see \Drupal\Core\Config\ConfigManagerInterface::getConfigEntitiesToChangeOnDependencyRemoval()
   */
  protected function addDependencyListsToForm(array &$form, $type, array $names, ConfigManagerInterface $config_manager, EntityManagerInterface $entity_manager) {
    // Get the dependent entities.
    $dependent_entities = $config_manager->getConfigEntitiesToChangeOnDependencyRemoval($type, $names);
    $entity_types = [];

    $form['entity_updates'] = [
      '#type' => 'details',
      '#title' => $this->t('Configuration updates'),
      '#description' => $this->t('The listed configuration will be updated.'),
      '#open' => TRUE,
      '#access' => FALSE,
    ];

    foreach ($dependent_entities['update'] as $entity) {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface  $entity */
      $entity_type_id = $entity->getEntityTypeId();
      if (!isset($form['entity_updates'][$entity_type_id])) {
        $entity_type = $entity_manager->getDefinition($entity_type_id);
        // Store the ID and label to sort the entity types and entities later.
        $label = $entity_type->getLabel();
        $entity_types[$entity_type_id] = $label;
        $form['entity_updates'][$entity_type_id] = [
          '#theme' => 'item_list',
          '#title' => $label,
          '#items' => [],
        ];
      }
      $form['entity_updates'][$entity_type_id]['#items'][$entity->id()] = $entity->label() ?: $entity->id();
    }
    if (!empty($dependent_entities['update'])) {
      $form['entity_updates']['#access'] = TRUE;

      // Add a weight key to the entity type sections.
      asort($entity_types, SORT_FLAG_CASE);
      $weight = 0;
      foreach ($entity_types as $entity_type_id => $label) {
        $form['entity_updates'][$entity_type_id]['#weight'] = $weight;
        // Sort the list of entity labels alphabetically.
        ksort($form['entity_updates'][$entity_type_id]['#items'], SORT_FLAG_CASE);
        $weight++;
      }
    }

    $form['entity_deletes'] = [
      '#type' => 'details',
      '#title' => $this->t('Configuration deletions'),
      '#description' => $this->t('The listed configuration will be deleted.'),
      '#open' => TRUE,
      '#access' => FALSE,
    ];

    foreach ($dependent_entities['delete'] as $entity) {
      $entity_type_id = $entity->getEntityTypeId();
      if (!isset($form['entity_deletes'][$entity_type_id])) {
        $entity_type = $entity_manager->getDefinition($entity_type_id);
        // Store the ID and label to sort the entity types and entities later.
        $label = $entity_type->getLabel();
        $entity_types[$entity_type_id] = $label;
        $form['entity_deletes'][$entity_type_id] = [
          '#theme' => 'item_list',
          '#title' => $label,
          '#items' => [],
        ];
      }
      $form['entity_deletes'][$entity_type_id]['#items'][$entity->id()] = $entity->label() ?: $entity->id();
    }
    if (!empty($dependent_entities['delete'])) {
      $form['entity_deletes']['#access'] = TRUE;

      // Add a weight key to the entity type sections.
      asort($entity_types, SORT_FLAG_CASE);
      $weight = 0;
      foreach ($entity_types as $entity_type_id => $label) {
        if (isset($form['entity_deletes'][$entity_type_id])) {
          $form['entity_deletes'][$entity_type_id]['#weight'] = $weight;
          // Sort the list of entity labels alphabetically.
          ksort($form['entity_deletes'][$entity_type_id]['#items'], SORT_FLAG_CASE);
          $weight++;
        }
      }
    }

  }

}
