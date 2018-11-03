<?php

namespace Drupal\Tests\file\Kernel;

use Drupal\file\Entity\File;

/**
 * File URI field item test.
 *
 * @group file
 *
 * @see \Drupal\file\Plugin\Field\FieldType\FileUriItem
 * @see \Drupal\file\FileUrl
 */
class FileUriItemTest extends FileManagedUnitTestBase {

  /**
   * Tests the file entity override of the URI field.
   */
  public function testCustomFileUriField() {
    $uri = 'public://druplicon.txt';

    // Create a new file entity.
    $file = File::create([
      'uid' => 1,
      'filename' => 'druplicon.txt',
      'uri' => $uri,
      'filemime' => 'text/plain',
      'status' => FILE_STATUS_PERMANENT,
    ]);
    file_put_contents($file->getFileUri(), 'hello world');

    $file->save();

    $this->assertSame($uri, $file->uri->value);
    $expected_url = base_path() . $this->siteDirectory . '/files/druplicon.txt';
    $this->assertSame($expected_url, $file->uri->url);
  }

}
