<?php

namespace Drupal\Tests\node\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests node owner functionality.
 *
 * @group Entity
 */
class NodeOwnerTest extends EntityKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'language'];

  protected function setUp() {
    parent::setUp();

    // Create the node bundles required for testing.
    $type = NodeType::create([
      'type' => 'page',
      'name' => 'page',
    ]);
    $type->save();

    // Enable two additional languages.
    ConfigurableLanguage::createFromLangcode('de')->save();
    ConfigurableLanguage::createFromLangcode('it')->save();

    $this->installSchema('node', 'node_access');
  }

  /**
   * Tests node owner functionality.
   */
  public function testOwner() {
    $user = $this->createUser();

    $container = \Drupal::getContainer();
    $container->get('current_user')->setAccount($user);

    // Create a test node.
    $english = Node::create([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'language' => 'en',
    ]);
    $english->save();

    $this->assertEqual($user->id(), $english->getOwnerId());

    $german = $english->addTranslation('de');
    $german->title = $this->randomString();
    $italian = $english->addTranslation('it');
    $italian->title = $this->randomString();

    // Node::preSave() sets owner to anonymous user if owner is nor set.
    $english->set('uid', ['target_id' => NULL]);
    $german->set('uid', ['target_id' => NULL]);
    $italian->set('uid', ['target_id' => NULL]);

    // Entity::save() saves all translations!
    $italian->save();

    $this->assertEqual(0, $english->getOwnerId());
    $this->assertEqual(0, $german->getOwnerId());
    $this->assertEqual(0, $italian->getOwnerId());
  }

}
