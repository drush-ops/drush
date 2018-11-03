<?php

namespace Drupal\Core\Archiver;

/**
 * Defines a archiver implementation for .zip files.
 *
 * @link http://php.net/zip
 */
class Zip implements ArchiverInterface {

  /**
   * The underlying ZipArchive instance that does the heavy lifting.
   *
   * @var \ZipArchive
   */
  protected $zip;

  /**
   * Constructs a Zip object.
   *
   * @param string $file_path
   *   The full system path of the archive to manipulate. Only local files
   *   are supported. If the file does not yet exist, it will be created if
   *   appropriate.
   *
   * @throws \Drupal\Core\Archiver\ArchiverException
   */
  public function __construct($file_path) {
    $this->zip = new \ZipArchive();
    if ($this->zip->open($file_path) !== TRUE) {
      throw new ArchiverException(t('Cannot open %file_path', ['%file_path' => $file_path]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function add($file_path) {
    $this->zip->addFile($file_path);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function remove($file_path) {
    $this->zip->deleteName($file_path);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function extract($path, array $files = []) {
    if ($files) {
      $this->zip->extractTo($path, $files);
    }
    else {
      $this->zip->extractTo($path);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function listContents() {
    $files = [];
    for ($i = 0; $i < $this->zip->numFiles; $i++) {
      $files[] = $this->zip->getNameIndex($i);
    }
    return $files;
  }

  /**
   * Retrieves the zip engine itself.
   *
   * In some cases it may be necessary to directly access the underlying
   * ZipArchive object for implementation-specific logic. This is for advanced
   * use only as it is not shared by other implementations of ArchiveInterface.
   *
   * @return \ZipArchive
   *   The ZipArchive object used by this object.
   */
  public function getArchive() {
    return $this->zip;
  }

}
