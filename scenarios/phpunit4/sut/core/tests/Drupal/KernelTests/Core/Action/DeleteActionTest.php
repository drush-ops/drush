<?php

namespace Drupal\KernelTests\Core\Action;

use Drupal\Core\Action\Plugin\Action\Derivative\EntityDeleteActionDeriver;
use Drupal\entity_test\Entity\EntityTestMulRevPub;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Entity\Action;
use Drupal\user\Entity\User;

/**
 * @group Action
 */
class DeleteActionTest extends KernelTestBase {

  protected $testUser;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'entity_test', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('entity_test_mulrevpub');
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences', 'key_value_expire']);

    $this->testUser = User::create([
      'name' => 'foobar',
      'mail' => 'foobar@example.com',
    ]);
    $this->testUser->save();
    \Drupal::service('current_user')->setAccount($this->testUser);
  }

  /**
   * @covers \Drupal\Core\Action\Plugin\Action\Derivative\EntityDeleteActionDeriver::getDerivativeDefinitions
   */
  public function testGetDerivativeDefinitions() {
    $deriver = new EntityDeleteActionDeriver(\Drupal::entityTypeManager(), \Drupal::translation());
    $this->assertEquals([
      'entity_test_mulrevpub' => [
        'type' => 'entity_test_mulrevpub',
        'label' => 'Delete test entity - revisions, data table, and published interface',
        'action_label' => 'Delete',
        'confirm_form_route_name' => 'entity.entity_test_mulrevpub.delete_multiple_form',
      ],
      'entity_test_rev' => [
        'type' => 'entity_test_rev',
        'label' => 'Delete test entity - revisions',
        'action_label' => 'Delete',
        'confirm_form_route_name' => 'entity.entity_test_rev.delete_multiple_form',
      ],
    ], $deriver->getDerivativeDefinitions([
      'action_label' => 'Delete',
    ]));
  }

  /**
   * @covers \Drupal\Core\Action\Plugin\Action\DeleteAction::execute
   */
  public function testDeleteAction() {
    $entity = EntityTestMulRevPub::create(['name' => 'test']);
    $entity->save();

    $action = Action::create([
      'id' => 'entity_delete_action',
      'plugin' => 'entity:delete_action:entity_test_mulrevpub',
    ]);
    $action->save();

    $action->execute([$entity]);
    $this->assertArraySubset(['module' => ['entity_test']], $action->getDependencies());

    /** @var \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store */
    $temp_store = \Drupal::service('tempstore.private');
    $store_entries = $temp_store->get('entity_delete_multiple_confirm')->get($this->testUser->id() . ':entity_test_mulrevpub');
    $this->assertArraySubset([$this->testUser->id() => ['en' => 'en']], $store_entries);
  }

}
