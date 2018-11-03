<?php

namespace Drupal\FunctionalTests\Installer;

/**
 * Verifies that profiles with hook_install() can't be installed from config.
 *
 * @group Installer
 */
class InstallerExistingConfigSyncDirectoryProfileHookInstall extends InstallerExistingConfigTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing_config_install_multilingual';

  /**
   * {@inheritdoc}
   */
  protected $existingSyncDirectory = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function visitInstaller() {
    // Create an .install file with a hook_install() implementation.
    $path = $this->siteDirectory . '/profiles/' . $this->profile;
    $contents = <<<EOF
<?php

function testing_config_install_multilingual_install() {
}
EOF;
    file_put_contents("$path/{$this->profile}.install", $contents);
    parent::visitInstaller();
  }

  /**
   * Installer step: Select installation profile.
   */
  protected function setUpProfile() {
    // This is the form we are testing so wait until the test method to do
    // assertions.
    return;
  }

  /**
   * Installer step: Requirements problem.
   */
  protected function setUpRequirementsProblem() {
    // This form will never be reached.
    return;
  }

  /**
   * Installer step: Configure settings.
   */
  protected function setUpSettings() {
    // This form will never be reached.
    return;
  }

  /**
   * Final installer step: Configure site.
   */
  protected function setUpSite() {
    // This form will never be reached.
    return;
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigTarball() {
    return __DIR__ . '/../../../fixtures/config_install/multilingual.tar.gz';
  }

  /**
   * Tests installing from config is not available due to hook_INSTALL().
   */
  public function testConfigSync() {
    $this->assertSession()->titleEquals('Select an installation profile | Drupal');
    $this->assertSession()->responseNotContains('Use existing configuration');

    // Remove the install hook and the option to install from existing
    // configuration will be available.
    unlink("{$this->siteDirectory}/profiles/{$this->profile}/{$this->profile}.install");
    $this->getSession()->reload();
    $this->assertSession()->titleEquals('Select an installation profile | Drupal');
    $this->assertSession()->responseContains('Use existing configuration');
  }

}
