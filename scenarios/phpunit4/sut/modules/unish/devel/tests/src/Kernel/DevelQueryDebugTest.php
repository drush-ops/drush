<?php

namespace Drupal\Tests\devel\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests query debug.
 *
 * @group devel
 */
class DevelQueryDebugTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['devel', 'system', 'user'];

  /**
   * The user used in test.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $develUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installConfig(['system', 'devel']);
    $this->installEntitySchema('user');

    $devel_role = Role::create([
      'id' => 'admin',
      'permissions' => ['access devel information'],
    ]);
    $devel_role->save();

    $this->develUser = User::create([
      'name' => $this->randomMachineName(),
      'roles' => [$devel_role->id()],
    ]);
    $this->develUser->save();
  }

  /**
   * Tests devel_query_debug_alter() for select queries.
   */
  public function testSelectQueryDebugTag() {
    // Clear the messages stack.
    $this->getDrupalMessages();

    // Ensures that no debug message is shown to user without the adequate
    // permissions.
    $query = \Drupal::database()->select('users', 'u');
    $query->fields('u', ['uid']);
    $query->addTag('debug');
    $query->execute();

    $messages = $this->getDrupalMessages();
    $this->assertEmpty($messages);

    // Ensures that the SQL debug message is shown to user with the adequate
    // permissions. We expect only one status message containing the SQL for
    // the debugged query.
    \Drupal::currentUser()->setAccount($this->develUser);
    $expected_message = "SELECT u.uid AS uid\nFROM \n{users} u";

    $query = \Drupal::database()->select('users', 'u');
    $query->fields('u', ['uid']);
    $query->addTag('debug');
    $query->execute();

    $messages = $this->getDrupalMessages();
    $this->assertTrue(!empty($messages['status']));
    $this->assertCount(1, $messages['status']);
    $this->assertEquals(strip_tags($messages['status'][0]), $expected_message);
  }

  /**
   * Tests devel_query_debug_alter() for entity queries.
   */
  public function testEntityQueryDebugTag() {
    // Clear the messages stack.
    $this->getDrupalMessages();

    // Ensures that no debug message is shown to user without the adequate
    // permissions.
    $query = \Drupal::entityQuery('user');
    $query->addTag('debug');
    $query->execute();

    $messages = $this->getDrupalMessages();
    $this->assertEmpty($messages);

    // Ensures that the SQL debug message is shown to user with the adequate
    // permissions. We expect only one status message containing the SQL for
    // the debugged entity query.
    \Drupal::currentUser()->setAccount($this->develUser);
    $expected_message = "SELECT base_table.uid AS uid, base_table.uid AS base_table_uid\nFROM \n{users} base_table";

    $query = \Drupal::entityQuery('user');
    $query->addTag('debug');
    $query->execute();

    $messages = $this->getDrupalMessages();
    $this->assertTrue(!empty($messages['status']));
    $this->assertCount(1, $messages['status']);
    $this->assertEquals(strip_tags($messages['status'][0]), $expected_message);
  }

  /**
   * Retrieves the drupal messages.
   *
   * @return array
   *   The messages
   */
  protected function getDrupalMessages() {
    return drupal_get_messages();
  }

}
