<?php

namespace Drupal\Tests\file\Kernel\Formatter;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the default file formatter.
 *
 * @group field
 */
class FileEntityFormatterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['file', 'user'];

  /**
   * The files.
   *
   * @var array
   */
  protected $files;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('file');

    $this->files = [];
    file_put_contents('public://file.png', str_repeat('t', 10));
    $file = File::create([
      'uri' => 'public://file.png',
      'filename' => 'file.png',
    ]);
    $file->save();
    $this->files[] = $file;

    file_put_contents('public://file.tar', str_repeat('t', 200));
    $file = File::create([
      'uri' => 'public://file.tar',
      'filename' => 'file.tar',
    ]);
    $file->save();
    $this->files[] = $file;

    file_put_contents('public://file.tar.gz', str_repeat('t', 40000));
    $file = File::create([
      'uri' => 'public://file.tar.gz',
      'filename' => 'file.tar.gz',
    ]);
    $file->save();
    $this->files[] = $file;

    file_put_contents('public://file', str_repeat('t', 8000000));
    $file = File::create([
      'uri' => 'public://file',
      'filename' => 'file',
    ]);
    $file->save();
    $this->files[] = $file;
  }

  /**
   * Tests the file_link field formatter.
   */
  public function testFormatterFileLink() {
    $entity_display = EntityViewDisplay::create([
      'targetEntityType' => 'file',
      'bundle' => 'file',
    ]);
    $entity_display->setComponent('filename', ['type' => 'file_link']);

    $build = $entity_display->buildMultiple($this->files)[0]['filename'][0];
    $this->assertEqual('file.png', $build['#title']);
    $this->assertEqual(Url::fromUri(file_create_url('public://file.png')), $build['#url']);
  }

  /**
   * Tests the file_link field formatter.
   */
  public function testFormatterFileUri() {
    $entity_display = EntityViewDisplay::create([
      'targetEntityType' => 'file',
      'bundle' => 'file',
    ]);
    $entity_display->setComponent('uri', ['type' => 'file_uri']);

    $build = $entity_display->buildMultiple($this->files)[0]['uri'][0];
    $this->assertEqual('public://file.png', $build['#markup']);

    $entity_display->setComponent('uri', ['type' => 'file_uri', 'settings' => ['file_download_path' => TRUE]]);
    $build = $entity_display->buildMultiple($this->files)[0]['uri'][0];
    $this->assertEqual(file_create_url('public://file.png'), $build['#markup']);

    $entity_display->setComponent('uri', ['type' => 'file_uri', 'settings' => ['file_download_path' => TRUE, 'link_to_file' => TRUE]]);
    $build = $entity_display->buildMultiple($this->files)[0]['uri'][0];
    $this->assertEqual(file_create_url('public://file.png'), $build['#title']);
    $this->assertEqual(Url::fromUri(file_create_url('public://file.png')), $build['#url']);
  }

  /**
   * Tests the file_extension field formatter.
   */
  public function testFormatterFileExtension() {
    $entity_display = EntityViewDisplay::create([
      'targetEntityType' => 'file',
      'bundle' => 'file',
    ]);
    $entity_display->setComponent('filename', ['type' => 'file_extension']);

    $expected = ['png', 'tar', 'gz', ''];
    foreach (array_values($this->files) as $i => $file) {
      $build = $entity_display->build($file);
      $this->assertEqual($expected[$i], $build['filename'][0]['#markup']);
    }

    $entity_display->setComponent('filename', ['type' => 'file_extension', 'settings' => ['extension_detect_tar' => TRUE]]);

    $expected = ['png', 'tar', 'tar.gz', ''];
    foreach (array_values($this->files) as $i => $file) {
      $build = $entity_display->build($file);
      $this->assertEqual($expected[$i], $build['filename'][0]['#markup']);
    }
  }

  /**
   * Tests the file_extension field formatter.
   */
  public function testFormatterFileMime() {
    $entity_display = EntityViewDisplay::create([
      'targetEntityType' => 'file',
      'bundle' => 'file',
    ]);
    $entity_display->setComponent('filemime', ['type' => 'file_filemime', 'settings' => ['filemime_image' => TRUE]]);

    foreach (array_values($this->files) as $i => $file) {
      $build = $entity_display->build($file);
      $this->assertEqual('image__file_icon', $build['filemime'][0]['#theme']);
      $this->assertEqual(spl_object_hash($file), spl_object_hash($build['filemime'][0]['#file']));
    }
  }

  /**
   * Tests the file_size field formatter.
   */
  public function testFormatterFileSize() {
    $entity_display = EntityViewDisplay::create([
      'targetEntityType' => 'file',
      'bundle' => 'file',
    ]);
    $entity_display->setComponent('filesize', ['type' => 'file_size']);

    $expected = ['10 bytes', '200 bytes', '39.06 KB', '7.63 MB'];
    foreach (array_values($this->files) as $i => $file) {
      $build = $entity_display->build($file);
      $this->assertEqual($expected[$i], $build['filesize'][0]['#markup']);
    }
  }

}
