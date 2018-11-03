<?php

namespace Drupal\node\Plugin\migrate\source\d7;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Provides Drupal 7 node entity translations source plugin.
 *
 * @MigrateSource(
 *   id = "d7_node_entity_translation",
 *   source_module = "entity_translation"
 * )
 */
class NodeEntityTranslation extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('entity_translation', 'et')
      ->fields('et', [
        'entity_id',
        'revision_id',
        'language',
        'source',
        'uid',
        'status',
        'created',
        'changed',
      ])
      ->fields('n', [
        'title',
        'type',
        'promote',
        'sticky',
      ])
      ->fields('nr', [
        'log',
        'timestamp',
      ])
      ->condition('et.entity_type', 'node')
      ->condition('et.source', '', '<>');

    $query->addField('nr', 'uid', 'revision_uid');

    $query->innerJoin('node', 'n', 'n.nid = et.entity_id');
    $query->innerJoin('node_revision', 'nr', 'nr.vid = et.revision_id');

    if (isset($this->configuration['node_type'])) {
      $query->condition('n.type', $this->configuration['node_type']);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $nid = $row->getSourceProperty('entity_id');
    $vid = $row->getSourceProperty('revision_id');
    $type = $row->getSourceProperty('type');
    $language = $row->getSourceProperty('language');

    // Get Field API field values.
    foreach ($this->getFields('node', $type) as $field_name => $field) {
      // Ensure we're using the right language if the entity is translatable.
      $field_language = $field['translatable'] ? $language : NULL;
      $row->setSourceProperty($field_name, $this->getFieldValues('node', $field_name, $nid, $vid, $field_language));
    }

    // If the node title was replaced by a real field using the Drupal 7 Title
    // module, use the field value instead of the node title.
    if ($this->moduleExists('title')) {
      $title_field = $row->getSourceProperty('title_field');
      if (isset($title_field[0]['value'])) {
        $row->setSourceProperty('title', $title_field[0]['value']);
      }
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'entity_id' => $this->t('Entity ID'),
      'revision_id' => $this->t('Revision ID'),
      'language' => $this->t('Node translation language'),
      'source' => $this->t('Node translation source language'),
      'uid' => $this->t('Node translation authored by (uid)'),
      'status' => $this->t('Published'),
      'created' => $this->t('Created timestamp'),
      'changed' => $this->t('Modified timestamp'),
      'title' => $this->t('Node title'),
      'type' => $this->t('Node type'),
      'promote' => $this->t('Promoted to front page'),
      'sticky' => $this->t('Sticky at top of lists'),
      'log' => $this->t('Revision log'),
      'timestamp' => $this->t('The timestamp the latest revision of this node was created.'),
      'revision_uid' => $this->t('Revision authored by (uid)'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'entity_id' => [
        'type' => 'integer',
        'alias' => 'et',
      ],
      'language' => [
        'type' => 'string',
        'alias' => 'et',
      ],
    ];
  }

}
