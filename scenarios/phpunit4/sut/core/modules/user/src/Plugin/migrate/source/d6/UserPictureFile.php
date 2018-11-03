<?php

namespace Drupal\user\Plugin\migrate\source\d6;

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Drupal\migrate\Row;

/**
 * Drupal 6 user picture source from database.
 *
 * @MigrateSource(
 *   id = "d6_user_picture_file",
 *   source_module = "user"
 * )
 */
class UserPictureFile extends DrupalSqlBase {

  /**
   * The file directory path.
   *
   * @var string
   */
  protected $filePath;

  /**
   * The temporary file path.
   *
   * @var string
   */
  protected $tempFilePath;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('users', 'u')
      ->condition('u.picture', '', '<>')
      ->fields('u', ['uid', 'picture']);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function initializeIterator() {
    $site_path = isset($this->configuration['site_path']) ? $this->configuration['site_path'] : 'sites/default';
    $this->filePath = $this->variableGet('file_directory_path', $site_path . '/files') . '/';
    $this->tempFilePath = $this->variableGet('file_directory_temp', '/tmp') . '/';
    return parent::initializeIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $row->setSourceProperty('filename', basename($row->getSourceProperty('picture')));
    $row->setSourceProperty('file_directory_path', $this->filePath);
    $row->setSourceProperty('temp_directory_path', $this->tempFilePath);
    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'picture' => "Path to the user's uploaded picture.",
      'filename' => 'The picture filename.',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['uid']['type'] = 'integer';
    return $ids;
  }

}
