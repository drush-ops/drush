<?php

namespace Drupal\Tests\system\Kernel\System;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests system_get_info().
 *
 * @group system
 */
class SystemGetInfoTest extends KernelTestBase {

  public static $modules = ['system'];

  /**
   * Tests system_get_info().
   */
  public function testSystemGetInfo() {
    $system_module_info = system_get_info('module', 'system');
    $this->assertSame('System', $system_module_info['name']);
    $this->assertSame(['system' => $system_module_info], system_get_info('module'));

    // The User module is not installed so system_get_info() should return
    // an empty array.
    $this->assertSame([], system_get_info('module', 'user'));

    // Install the User module and check system_get_info() returns the correct
    // information.
    $this->container->get('module_installer')->install(['user']);
    $user_module_info = system_get_info('module', 'user');
    $this->assertSame('User', $user_module_info['name']);
    $this->assertSame(['system' => $system_module_info, 'user' => $user_module_info], system_get_info('module'));

    // Test theme info. There are no themes installed yet.
    $this->assertSame([], system_get_info('theme', 'stable'));
    $this->assertSame([], system_get_info('theme'));
    $this->container->get('theme_installer')->install(['stable']);
    $stable_theme_info = system_get_info('theme', 'stable');
    $this->assertSame('Stable', $stable_theme_info['name']);
    $this->assertSame(['stable' => $stable_theme_info], system_get_info('theme'));
  }

}
