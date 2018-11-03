<?php

namespace Drupal\Tests\migrate\Kernel\process;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\KernelTests\Core\File\FileTestBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\process\Download;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use GuzzleHttp\Client;

/**
 * Tests the download process plugin.
 *
 * @group migrate
 */
class DownloadTest extends FileTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->container->get('stream_wrapper_manager')->registerWrapper('temporary', 'Drupal\Core\StreamWrapper\TemporaryStream', StreamWrapperInterface::LOCAL_NORMAL);
  }

  /**
   * Tests a download that overwrites an existing local file.
   */
  public function testOverwritingDownload() {
    // Create a pre-existing file at the destination, to test overwrite behavior.
    $destination_uri = $this->createUri('existing_file.txt');

    // Test destructive download.
    $actual_destination = $this->doTransform($destination_uri);
    $this->assertSame($destination_uri, $actual_destination, 'Import returned a destination that was not renamed');
    $this->assertFileNotExists('public://existing_file_0.txt', 'Import did not rename the file');
  }

  /**
   * Tests a download that renames the downloaded file if there's a collision.
   */
  public function testNonDestructiveDownload() {
    // Create a pre-existing file at the destination, to test overwrite behavior.
    $destination_uri = $this->createUri('another_existing_file.txt');

    // Test non-destructive download.
    $actual_destination = $this->doTransform($destination_uri, ['file_exists' => 'rename']);
    $this->assertSame('public://another_existing_file_0.txt', $actual_destination, 'Import returned a renamed destination');
    $this->assertFileExists($actual_destination, 'Downloaded file was created');
  }

  /**
   * Tests that an exception is thrown if the destination URI is not writable.
   */
  public function testWriteProtectedDestination() {
    // Create a pre-existing file at the destination, to test overwrite behavior.
    $destination_uri = $this->createUri('not-writable.txt');

    // Make the destination non-writable.
    $this->container
      ->get('file_system')
      ->chmod($destination_uri, 0444);

    // Pass or fail, we'll need to make the file writable again so the test
    // can clean up after itself.
    $fix_permissions = function () use ($destination_uri) {
      $this->container
        ->get('file_system')
        ->chmod($destination_uri, 0755);
    };

    try {
      $this->doTransform($destination_uri);
      $fix_permissions();
      $this->fail('MigrateException was not thrown for non-writable destination URI.');
    }
    catch (MigrateException $e) {
      $this->assertTrue(TRUE, 'MigrateException was thrown for non-writable destination URI.');
      $fix_permissions();
    }
  }

  /**
   * Runs an input value through the download plugin.
   *
   * @param string $destination_uri
   *   The destination URI to download to.
   * @param array $configuration
   *   (optional) Configuration for the download plugin.
   *
   * @return string
   *   The local URI of the downloaded file.
   */
  protected function doTransform($destination_uri, $configuration = []) {
    // Prepare a mock HTTP client.
    $this->container->set('http_client', $this->getMock(Client::class));

    // Instantiate the plugin statically so it can pull dependencies out of
    // the container.
    $plugin = Download::create($this->container, $configuration, 'download', []);

    // Execute the transformation.
    $executable = $this->getMock(MigrateExecutableInterface::class);
    $row = new Row([], []);

    // Return the downloaded file's local URI.
    $value = [
      'http://drupal.org/favicon.ico',
      $destination_uri,
    ];
    return $plugin->transform($value, $executable, $row, 'foobaz');
  }

}
