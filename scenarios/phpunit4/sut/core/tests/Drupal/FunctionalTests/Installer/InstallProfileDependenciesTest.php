<?php

namespace Drupal\FunctionalTests\Installer;

use Drupal\Core\Extension\ModuleUninstallValidatorException;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that an install profile can require modules.
 *
 * @group Installer
 */
class InstallProfileDependenciesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing_install_profile_dependencies';

  /**
   * Tests that an install profile can require modules.
   */
  public function testUninstallingModules() {
    $user = $this->drupalCreateUser(['administer modules']);
    $this->drupalLogin($user);
    $this->drupalGet('admin/modules/uninstall');
    $this->assertSession()->fieldDisabled('uninstall[dblog]');
    $this->getSession()->getPage()->checkField('uninstall[ban]');
    $this->click('#edit-submit');
    // Click the confirm button.
    $this->click('#edit-submit');
    $this->assertSession()->responseContains('The selected modules have been uninstalled.');
    // We've uninstalled a module therefore we need to rebuild the container in
    // the test runner.
    $this->rebuildContainer();
    $this->assertFalse($this->container->get('module_handler')->moduleExists('ban'));
    try {
      $this->container->get('module_installer')->uninstall(['dblog']);
      $this->fail('Uninstalled dblog module.');
    }
    catch (ModuleUninstallValidatorException $e) {
      $this->assertContains('The Testing install profile dependencies module is required', $e->getMessage());
    }
  }

}
