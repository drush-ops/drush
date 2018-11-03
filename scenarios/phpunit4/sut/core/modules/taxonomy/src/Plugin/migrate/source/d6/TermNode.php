<?php

namespace Drupal\taxonomy\Plugin\migrate\source\d6;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Source returning tids from the term_node table for the current revision.
 *
 * @MigrateSource(
 *   id = "d6_term_node",
 *   source_module = "taxonomy"
 * )
 */
class TermNode extends DrupalSqlBase {

  /**
   * The join options between the node and the term node table.
   */
  const JOIN = 'tn.vid = n.vid';

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('term_node', 'tn')
      ->distinct()
      ->fields('tn', ['nid', 'vid'])
      ->fields('n', ['type']);
    // Because this is an inner join it enforces the current revision.
    $query->innerJoin('term_data', 'td', 'td.tid = tn.tid AND td.vid = :vid', [':vid' => $this->configuration['vid']]);
    $query->innerJoin('node', 'n', static::JOIN);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'nid' => $this->t('The node revision ID.'),
      'vid' => $this->t('The node revision ID.'),
      'tid' => $this->t('The term ID.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Select the terms belonging to the revision selected.
    $query = $this->select('term_node', 'tn')
      ->fields('tn', ['tid'])
      ->condition('n.nid', $row->getSourceProperty('nid'));
    $query->join('node', 'n', static::JOIN);
    $query->innerJoin('term_data', 'td', 'td.tid = tn.tid AND td.vid = :vid', [':vid' => $this->configuration['vid']]);
    $row->setSourceProperty('tid', $query->execute()->fetchCol());
    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['vid']['type'] = 'integer';
    $ids['vid']['alias'] = 'tn';
    return $ids;
  }

}
