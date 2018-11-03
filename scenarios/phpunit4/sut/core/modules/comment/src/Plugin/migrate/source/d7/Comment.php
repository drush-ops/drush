<?php

namespace Drupal\comment\Plugin\migrate\source\d7;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Drupal 7 comment source from database.
 *
 * @MigrateSource(
 *   id = "d7_comment",
 *   source_module = "comment"
 * )
 */
class Comment extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('comment', 'c')->fields('c');
    $query->innerJoin('node', 'n', 'c.nid = n.nid');
    $query->addField('n', 'type', 'node_type');
    $query->orderBy('c.created');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $cid = $row->getSourceProperty('cid');

    $node_type = $row->getSourceProperty('node_type');
    $comment_type = 'comment_node_' . $node_type;
    $row->setSourceProperty('comment_type', 'comment_node_' . $node_type);

    foreach (array_keys($this->getFields('comment', $comment_type)) as $field) {
      $row->setSourceProperty($field, $this->getFieldValues('comment', $field, $cid));
    }

    // If the comment subject was replaced by a real field using the Drupal 7
    // Title module, use the field value instead of the comment subject.
    if ($this->moduleExists('title')) {
      $subject_field = $row->getSourceProperty('subject_field');
      if (isset($subject_field[0]['value'])) {
        $row->setSourceProperty('subject', $subject_field[0]['value']);
      }
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'cid' => $this->t('Comment ID.'),
      'pid' => $this->t('Parent comment ID. If set to 0, this comment is not a reply to an existing comment.'),
      'nid' => $this->t('The {node}.nid to which this comment is a reply.'),
      'uid' => $this->t('The {users}.uid who authored the comment. If set to 0, this comment was created by an anonymous user.'),
      'subject' => $this->t('The comment title.'),
      'comment' => $this->t('The comment body.'),
      'hostname' => $this->t("The author's host name."),
      'created' => $this->t('The time that the comment was created, as a Unix timestamp.'),
      'changed' => $this->t('The time that the comment was edited by its author, as a Unix timestamp.'),
      'status' => $this->t('The published status of a comment. (0 = Published, 1 = Not Published)'),
      'format' => $this->t('The {filter_formats}.format of the comment body.'),
      'thread' => $this->t("The vancode representation of the comment's place in a thread."),
      'name' => $this->t("The comment author's name. Uses {users}.name if the user is logged in, otherwise uses the value typed into the comment form."),
      'mail' => $this->t("The comment author's email address from the comment form, if user is anonymous, and the 'Anonymous users may/must leave their contact information' setting is turned on."),
      'homepage' => $this->t("The comment author's home page address from the comment form, if user is anonymous, and the 'Anonymous users may/must leave their contact information' setting is turned on."),
      'type' => $this->t("The {node}.type to which this comment is a reply."),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['cid']['type'] = 'integer';
    return $ids;
  }

}
