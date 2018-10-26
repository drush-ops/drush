<?php

namespace Drupal\Tests\devel\Kernel;

use Drupal\block\Entity\Block;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Entity\Menu;

/**
 * Tests Devel enforced dependencies.
 *
 * @group devel
 */
class DevelEnforcedDependenciesTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['devel', 'block', 'user', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig('devel');
    // For uninstall to work.
    $this->installSchema('user', ['users_data']);
  }

  /**
   * Tests devel menu enforced dependencies.
   */
  public function testMenuEnforcedDependencies() {
    /** @var \Drupal\Core\Config\ConfigManagerInterface $config_manager */
    $config_manager = $this->container->get('config.manager');

    // Ensure that the Devel menu has explicit enforced dependencies on devel
    // module.
    $menu = Menu::load('devel');
    $this->assertEquals(['enforced' => ['module' => ['devel']]], $menu->get('dependencies'));

    // Creates an instance of devel menu block so you can test if enforced
    // dependencies work properly with it.
    $block_id = strtolower($this->randomMachineName(8));

    $block = Block::create([
      'plugin' => 'system_menu_block:devel',
      'region' => 'sidebar_first',
      'id' => $block_id,
      'theme' => $this->config('system.theme')->get('default'),
      'label' => $this->randomMachineName(8),
      'visibility' => array(),
      'weight' => 0,
    ]);
    $block->save();

    // Ensure that the menu and block instance depend on devel module.
    $dependents = $config_manager->findConfigEntityDependents('module', ['devel']);
    $this->assertTrue(isset($dependents['system.menu.devel']));
    $this->assertTrue(isset($dependents['block.block.' . $block_id]));

    $this->container->get('module_installer')->uninstall(['devel']);

    // Ensure that the menu and block instance are deleted when the dependency
    // is uninstalled.
    $this->assertNull(Menu::load('devel'));
    $this->assertNull(Block::load($block_id));

    // Ensure that no config entities depend on devel once uninstalled.
    $dependents = $config_manager->findConfigEntityDependents('module', ['devel']);
    $this->assertFalse(isset($dependents['system.menu.devel']));
    $this->assertFalse(isset($dependents['block.block.' . $block_id]));
  }

}
