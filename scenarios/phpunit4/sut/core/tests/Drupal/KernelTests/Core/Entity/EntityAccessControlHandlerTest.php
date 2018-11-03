<?php

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestStringId;
use Drupal\entity_test\Entity\EntityTestDefaultAccess;
use Drupal\entity_test\Entity\EntityTestNoUuid;
use Drupal\entity_test\Entity\EntityTestLabel;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\user\Entity\User;

/**
 * Tests the entity access control handler.
 *
 * @coversDefaultClass \Drupal\Core\Entity\EntityAccessControlHandler
 * @group Entity
 */
class EntityAccessControlHandlerTest extends EntityLanguageTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_no_uuid');
    $this->installEntitySchema('entity_test_rev');
    $this->installEntitySchema('entity_test_string_id');
  }

  /**
   * Asserts entity access correctly grants or denies access.
   */
  public function assertEntityAccess($ops, AccessibleInterface $object, AccountInterface $account = NULL) {
    foreach ($ops as $op => $result) {
      $message = format_string("Entity access returns @result with operation '@op'.", [
        '@result' => !isset($result) ? 'null' : ($result ? 'true' : 'false'),
        '@op' => $op,
      ]);

      $this->assertEqual($result, $object->access($op, $account), $message);
    }
  }

  /**
   * Ensures user labels are accessible for everyone.
   */
  public function testUserLabelAccess() {
    // Set up a non-admin user.
    \Drupal::currentUser()->setAccount($this->createUser(['uid' => 2]));

    $anonymous_user = User::getAnonymousUser();
    $user = $this->createUser();

    // The current user is allowed to view the anonymous user label.
    $this->assertEntityAccess([
      'create' => FALSE,
      'update' => FALSE,
      'delete' => FALSE,
      'view' => FALSE,
      'view label' => TRUE,
    ], $anonymous_user);

    // The current user is allowed to view user labels.
    $this->assertEntityAccess([
      'create' => FALSE,
      'update' => FALSE,
      'delete' => FALSE,
      'view' => FALSE,
      'view label' => TRUE,
    ], $user);

    // Switch to a anonymous user account.
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(new AnonymousUserSession());

    // The anonymous user is allowed to view the anonymous user label.
    $this->assertEntityAccess([
      'create' => FALSE,
      'update' => FALSE,
      'delete' => FALSE,
      'view' => FALSE,
      'view label' => TRUE,
    ], $anonymous_user);

    // The anonymous user is allowed to view user labels.
    $this->assertEntityAccess([
      'create' => FALSE,
      'update' => FALSE,
      'delete' => FALSE,
      'view' => FALSE,
      'view label' => TRUE,
    ], $user);

    // Restore user account.
    $account_switcher->switchBack();
  }

  /**
   * Ensures entity access is properly working.
   */
  public function testEntityAccess() {
    // Set up a non-admin user that is allowed to view test entities.
    \Drupal::currentUser()->setAccount($this->createUser(['uid' => 2], ['view test entity']));

    // Use the 'entity_test_label' entity type in order to test the 'view label'
    // access operation.
    $entity = EntityTestLabel::create([
      'name' => 'test',
    ]);

    // The current user is allowed to view entities.
    $this->assertEntityAccess([
      'create' => FALSE,
      'update' => FALSE,
      'delete' => FALSE,
      'view' => TRUE,
      'view label' => TRUE,
    ], $entity);

    // The custom user is not allowed to perform any operation on test entities,
    // except for viewing their label.
    $custom_user = $this->createUser();
    $this->assertEntityAccess([
      'create' => FALSE,
      'update' => FALSE,
      'delete' => FALSE,
      'view' => FALSE,
      'view label' => TRUE,
    ], $entity, $custom_user);
  }

  /**
   * Ensures default entity access is checked when necessary.
   *
   * This ensures that the default checkAccess() implementation of the
   * entity access control handler is considered if hook_entity_access() has not
   * explicitly forbidden access. Therefore the default checkAccess()
   * implementation can forbid access, even after access was already explicitly
   * allowed by hook_entity_access().
   *
   * @see \Drupal\entity_test\EntityTestAccessControlHandler::checkAccess()
   * @see entity_test_entity_access()
   */
  public function testDefaultEntityAccess() {
    // Set up a non-admin user that is allowed to view test entities.
    \Drupal::currentUser()->setAccount($this->createUser(['uid' => 2], ['view test entity']));
    $entity = EntityTest::create([
        'name' => 'forbid_access',
      ]);

    // The user is denied access to the entity.
    $this->assertEntityAccess([
        'create' => FALSE,
        'update' => FALSE,
        'delete' => FALSE,
        'view' => FALSE,
      ], $entity);
  }

  /**
   * Ensures that the default handler is used as a fallback.
   */
  public function testEntityAccessDefaultController() {
    // The implementation requires that the global user id can be loaded.
    \Drupal::currentUser()->setAccount($this->createUser(['uid' => 2]));

    // Check that the default access control handler is used for entities that don't
    // have a specific access control handler defined.
    $handler = $this->container->get('entity.manager')->getAccessControlHandler('entity_test_default_access');
    $this->assertTrue($handler instanceof EntityAccessControlHandler, 'The default entity handler is used for the entity_test_default_access entity type.');

    $entity = EntityTestDefaultAccess::create();
    $this->assertEntityAccess([
      'create' => FALSE,
      'update' => FALSE,
      'delete' => FALSE,
      'view' => FALSE,
    ], $entity);
  }

  /**
   * Ensures entity access for entity translations is properly working.
   */
  public function testEntityTranslationAccess() {

    // Set up a non-admin user that is allowed to view test entity translations.
    \Drupal::currentUser()->setAccount($this->createUser(['uid' => 2], ['view test entity translations']));

    // Create two test languages.
    foreach (['foo', 'bar'] as $langcode) {
      ConfigurableLanguage::create([
        'id' => $langcode,
        'label' => $this->randomString(),
      ])->save();
    }

    $entity = EntityTest::create([
      'name' => 'test',
      'langcode' => 'foo',
    ]);
    $entity->save();

    $translation = $entity->addTranslation('bar');
    $this->assertEntityAccess([
      'view' => TRUE,
    ], $translation);
  }

  /**
   * Ensures the static access cache works correctly in the absence of an UUID.
   *
   * @see entity_test_entity_access()
   */
  public function testEntityWithoutUuidAccessCache() {
    $account = $this->createUser();

    $entity1 = EntityTestNoUuid::create([
      'name' => 'Accessible',
    ]);
    $entity1->save();

    $entity2 = EntityTestNoUuid::create([
      'name' => 'Inaccessible',
    ]);
    $entity2->save();

    $this->assertTrue($entity1->access('delete', $account), 'Entity 1 can be deleted.');
    $this->assertFalse($entity2->access('delete', $account), 'Entity 2 CANNOT be deleted.');

    $entity1
      ->setName('Inaccessible')
      ->setNewRevision();
    $entity1->save();

    $this->assertFalse($entity1->access('delete', $account), 'Entity 1 revision 2 CANNOT be deleted.');
  }

  /**
   * Ensures the static access cache works correctly with a UUID and revisions.
   *
   * @see entity_test_entity_access()
   */
  public function testEntityWithUuidAccessCache() {
    $account = $this->createUser();

    $entity1 = EntityTestRev::create([
      'name' => 'Accessible',
    ]);
    $entity1->save();

    $entity2 = EntityTestRev::create([
      'name' => 'Inaccessible',
    ]);
    $entity2->save();

    $this->assertTrue($entity1->access('delete', $account), 'Entity 1 can be deleted.');
    $this->assertFalse($entity2->access('delete', $account), 'Entity 2 CANNOT be deleted.');

    $entity1
      ->setName('Inaccessible')
      ->setNewRevision();
    $entity1->save();

    $this->assertFalse($entity1->access('delete', $account), 'Entity 1 revision 2 CANNOT be deleted.');
  }

  /**
   * Tests hook invocations.
   */
  public function testHooks() {
    $state = $this->container->get('state');
    $entity = EntityTest::create([
      'name' => 'test',
    ]);

    // Test hook_entity_create_access() and hook_ENTITY_TYPE_create_access().
    $entity->access('create');
    $this->assertEqual($state->get('entity_test_entity_create_access'), TRUE);
    $this->assertIdentical($state->get('entity_test_entity_create_access_context'), [
      'entity_type_id' => 'entity_test',
      'langcode' => LanguageInterface::LANGCODE_DEFAULT,
    ]);
    $this->assertEqual($state->get('entity_test_entity_test_create_access'), TRUE);

    // Test hook_entity_access() and hook_ENTITY_TYPE_access().
    $entity->access('view');
    $this->assertEqual($state->get('entity_test_entity_access'), TRUE);
    $this->assertEqual($state->get('entity_test_entity_test_access'), TRUE);
  }

  /**
   * Tests the default access handling for the ID and UUID fields.
   *
   * @covers ::fieldAccess
   * @dataProvider providerTestFieldAccess
   */
  public function testFieldAccess($entity_class, array $entity_create_values, $expected_id_create_access) {
    // Set up a non-admin user that is allowed to create and update test
    // entities.
    \Drupal::currentUser()->setAccount($this->createUser(['uid' => 2], ['administer entity_test content']));

    // Create the entity to test field access with.
    $entity = $entity_class::create($entity_create_values);

    // On newly-created entities, field access must allow setting the UUID
    // field.
    $this->assertTrue($entity->get('uuid')->access('edit'));
    $this->assertTrue($entity->get('uuid')->access('edit', NULL, TRUE)->isAllowed());
    // On newly-created entities, field access will not allow setting the ID
    // field if the ID is of type serial. It will allow access if it is of type
    // string.
    $this->assertEquals($expected_id_create_access, $entity->get('id')->access('edit'));
    $this->assertEquals($expected_id_create_access, $entity->get('id')->access('edit', NULL, TRUE)->isAllowed());

    // Save the entity and check that we can not update the ID or UUID fields
    // anymore.
    $entity->save();

    // If the ID has been set as part of the create ensure it has been set
    // correctly.
    if (isset($entity_create_values['id'])) {
      $this->assertSame($entity_create_values['id'], $entity->id());
    }
    // The UUID is hard-coded by the data provider.
    $this->assertSame('60e3a179-79ed-4653-ad52-5e614c8e8fbe', $entity->uuid());
    $this->assertFalse($entity->get('uuid')->access('edit'));
    $access_result = $entity->get('uuid')->access('edit', NULL, TRUE);
    $this->assertTrue($access_result->isForbidden());
    $this->assertEquals('The entity UUID cannot be changed.', $access_result->getReason());

    // Ensure the ID is still not allowed to be edited.
    $this->assertFalse($entity->get('id')->access('edit'));
    $access_result = $entity->get('id')->access('edit', NULL, TRUE);
    $this->assertTrue($access_result->isForbidden());
    $this->assertEquals('The entity ID cannot be changed.', $access_result->getReason());
  }

  public function providerTestFieldAccess() {
    return [
      'serial ID entity' => [
        EntityTest::class,
        [
          'name' => 'A test entity',
          'uuid' => '60e3a179-79ed-4653-ad52-5e614c8e8fbe',
        ],
        FALSE,
      ],
      'string ID entity' => [
        EntityTestStringId::class,
        [
          'id' => 'a_test_entity',
          'name' => 'A test entity',
          'uuid' => '60e3a179-79ed-4653-ad52-5e614c8e8fbe',
        ],
        TRUE,
      ],
    ];
  }

}
