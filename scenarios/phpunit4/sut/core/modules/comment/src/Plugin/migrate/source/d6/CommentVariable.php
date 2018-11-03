<?php

namespace Drupal\comment\Plugin\migrate\source\d6;

@trigger_error('CommentVariable is deprecated in Drupal 8.4.x and will be removed before Drupal 9.0.x. Use \Drupal\node\Plugin\migrate\source\d6\NodeType instead.', E_USER_DEPRECATED);

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Drupal\migrate\Plugin\migrate\source\DummyQueryTrait;

/**
 * @MigrateSource(
 *   id = "d6_comment_variable",
 *   source_module = "comment"
 * )
 *
 * @deprecated in Drupal 8.4.x, to be removed before Drupal 9.0.x. Use
 * \Drupal\node\Plugin\migrate\source\d6\NodeType instead.
 */
class CommentVariable extends DrupalSqlBase {

  use DummyQueryTrait;

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    return new \ArrayIterator($this->getCommentVariables());
  }

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE) {
    return count($this->getCommentVariables());
  }

  /**
   * Retrieves the values of the comment variables grouped by node type.
   *
   * @return array
   */
  protected function getCommentVariables() {
    $comment_prefixes = array_keys($this->commentPrefixes());
    $variables = [];
    $node_types = $this->select('node_type', 'nt')
      ->fields('nt', ['type'])
      ->execute()
      ->fetchCol();
    foreach ($node_types as $node_type) {
      foreach ($comment_prefixes as $prefix) {
        $variables[] = $prefix . '_' . $node_type;
      }
    }
    $return = [];
    $values = $this->select('variable', 'v')
      ->fields('v', ['name', 'value'])
      ->condition('name', $variables, 'IN')
      ->execute()
      ->fetchAllKeyed();
    foreach ($node_types as $node_type) {
      foreach ($comment_prefixes as $prefix) {
        $name = $prefix . '_' . $node_type;
        if (isset($values[$name])) {
          $return[$node_type][$prefix] = unserialize($values[$name]);
        }
      }
    }
    // The return key will not be used so move it inside the row. This could
    // not be done sooner because otherwise empty rows would be created with
    // just the node type in it.
    foreach ($return as $node_type => $data) {
      $return[$node_type]['node_type'] = $node_type;
      $return[$node_type]['comment_type'] = empty($data['comment_subject_field']) ?
        'comment_no_subject' : 'comment';
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return $this->commentPrefixes() + [
      'node_type' => $this->t('The node type'),
      'comment_type' => $this->t('The comment type'),
    ];
  }

  /**
   * Comment related data for fields.
   */
  protected function commentPrefixes() {
    return [
      'comment' => $this->t('Default comment setting'),
      'comment_default_mode' => $this->t('Default display mode'),
      'comment_default_order' => $this->t('Default display order'),
      'comment_default_per_page' => $this->t('Default comments per page'),
      'comment_controls' => $this->t('Comment controls'),
      'comment_anonymous' => $this->t('Anonymous commenting'),
      'comment_subject_field' => $this->t('Comment subject field'),
      'comment_preview' => $this->t('Preview comment'),
      'comment_form_location' => $this->t('Location of comment submission form'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['node_type']['type'] = 'string';
    return $ids;
  }

}
