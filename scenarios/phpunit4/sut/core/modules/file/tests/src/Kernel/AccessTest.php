<?php

namespace Drupal\Tests\file\Kernel;

use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests for the File access control.
 *
 * @group file
 */
class AccessTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['file', 'system', 'user'];

  /**
   * An authenticated user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user1;

  /**
   * An authenticated user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user2;

  /**
   * The file object used in the test.
   *
   * @var \Drupal\file\FileInterface
   */
  protected $file;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('system', 'sequences');

    $this->user1 = User::create([
      'name' => 'user1',
      'status' => 1,
    ]);
    $this->user1->save();

    $this->user2 = User::create([
      'name' => 'user2',
      'status' => 1,
    ]);
    $this->user2->save();

    $this->file = File::create([
      'uid' => $this->user1->id(),
      'filename' => 'druplicon.txt',
      'filemime' => 'text/plain',
    ]);
  }

  /**
   * Tests that only the file owner can delete or update a file.
   */
  public function testOnlyOwnerCanDeleteUpdateFile() {
    \Drupal::currentUser()->setAccount($this->user2);
    $this->assertFalse($this->file->access('delete'));
    $this->assertFalse($this->file->access('update'));

    \Drupal::currentUser()->setAccount($this->user1);
    $this->assertTrue($this->file->access('delete'));
    $this->assertTrue($this->file->access('update'));
  }

  /**
   * Tests file entity field access.
   *
   * @see \Drupal\file\FileAccessControlHandler::checkFieldAccess()
   */
  public function testCheckFieldAccess() {
    \Drupal::currentUser()->setAccount($this->user1);
    /** @var \Drupal\file\FileInterface $file */
    $file = File::create([
      'uri' => 'public://test.png',
    ]);
    // While creating a file entity access will be allowed for create-only
    // fields.
    $this->assertTrue($file->get('uri')->access('edit'));
    $this->assertTrue($file->get('filemime')->access('edit'));
    $this->assertTrue($file->get('filesize')->access('edit'));
    // Access to the status field is denied whilst creating a file entity.
    $this->assertFalse($file->get('status')->access('edit'));
    $file->save();
    // After saving the entity is no longer new and, therefore, access to
    // create-only fields and the status field will be denied.
    $this->assertFalse($file->get('uri')->access('edit'));
    $this->assertFalse($file->get('filemime')->access('edit'));
    $this->assertFalse($file->get('filesize')->access('edit'));
    $this->assertFalse($file->get('status')->access('edit'));
  }

  /**
   * Tests create access checks.
   */
  public function testCreateAccess() {
    // Anonymous users can create a file by default.
    $this->assertFalse($this->file->access('create'));

    // Authenticated users can create a file by default.
    \Drupal::currentUser()->setAccount($this->user1);
    $this->assertFalse($this->file->access('create'));
  }

  /**
   * Tests cacheability metadata.
   */
  public function testFileCacheability() {
    $file = File::create([
      'filename' => 'green-scarf',
      'uri' => 'private://green-scarf',
      'filemime' => 'text/plain',
      'status' => FILE_STATUS_PERMANENT,
    ]);
    $file->save();
    \Drupal::service('session')->set('anonymous_allowed_file_ids', [$file->id() => $file->id()]);

    $account = User::getAnonymousUser();
    $file->setOwnerId($account->id())->save();
    $this->assertSame(['session', 'user'], $file->access('view', $account, TRUE)->getCacheContexts());
    $this->assertSame(['session', 'user'], $file->access('download', $account, TRUE)->getCacheContexts());

    $account = $this->user1;
    $file->setOwnerId($account->id())->save();
    $this->assertSame(['user'], $file->access('view', $account, TRUE)->getCacheContexts());
    $this->assertSame(['user'], $file->access('download', $account, TRUE)->getCacheContexts());
  }

}
