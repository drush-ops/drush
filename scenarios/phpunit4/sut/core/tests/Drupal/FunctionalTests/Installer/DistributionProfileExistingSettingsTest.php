<?php

namespace Drupal\FunctionalTests\Installer;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests distribution profile support with existing settings.
 *
 * @group Installer
 */
class DistributionProfileExistingSettingsTest extends InstallerTestBase {

  /**
   * The distribution profile info.
   *
   * @var array
   */
  protected $info;

  /**
   * {@inheritdoc}
   */
  protected function prepareEnvironment() {
    parent::prepareEnvironment();
    $this->info = [
      'type' => 'profile',
      'core' => \Drupal::CORE_COMPATIBILITY,
      'name' => 'Distribution profile',
      'distribution' => [
        'name' => 'My Distribution',
        'install' => [
          'theme' => 'bartik',
        ],
      ],
    ];
    // File API functions are not available yet.
    $path = $this->siteDirectory . '/profiles/mydistro';
    mkdir($path, 0777, TRUE);
    file_put_contents("$path/mydistro.info.yml", Yaml::encode($this->info));

    // Pre-configure hash salt.
    // Any string is valid, so simply use the class name of this test.
    $this->settings['settings']['hash_salt'] = (object) [
      'value' => __CLASS__,
      'required' => TRUE,
    ];

    // Pre-configure database credentials.
    $connection_info = Database::getConnectionInfo();
    unset($connection_info['default']['pdo']);
    unset($connection_info['default']['init_commands']);

    $this->settings['databases']['default'] = (object) [
      'value' => $connection_info,
      'required' => TRUE,
    ];

    // Use the kernel to find the site path because the site.path service should
    // not be available at this point in the install process.
    $site_path = DrupalKernel::findSitePath(Request::createFromGlobals());
    // Pre-configure config directories.
    $this->settings['config_directories'] = [
      CONFIG_SYNC_DIRECTORY => (object) [
        'value' => $site_path . '/files/config_staging',
        'required' => TRUE,
      ],
    ];
    mkdir($this->settings['config_directories'][CONFIG_SYNC_DIRECTORY]->value, 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpLanguage() {
    // Make settings file not writable.
    $filename = $this->siteDirectory . '/settings.php';
    // Make the settings file read-only.
    // Not using File API; a potential error must trigger a PHP warning.
    chmod($filename, 0444);

    // Verify that the distribution name appears.
    $this->assertRaw($this->info['distribution']['name']);
    // Verify that the requested theme is used.
    $this->assertRaw($this->info['distribution']['install']['theme']);
    // Verify that the "Choose profile" step does not appear.
    $this->assertNoText('profile');

    parent::setUpLanguage();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpProfile() {
    // This step is skipped, because there is a distribution profile.
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpSettings() {
    // This step should not appear, since settings.php is fully configured
    // already.
  }

  /**
   * Confirms that the installation succeeded.
   */
  public function testInstalled() {
    $this->assertUrl('user/1');
    $this->assertResponse(200);
    // Confirm that we are logged-in after installation.
    $this->assertText($this->rootUser->getUsername());

    // Confirm that Drupal recognizes this distribution as the current profile.
    $this->assertEqual(\Drupal::installProfile(), 'mydistro');
    $this->assertArrayNotHasKey('install_profile', Settings::getAll(), 'The install profile has not been written to settings.php.');
    $this->assertEqual($this->config('core.extension')->get('profile'), 'mydistro', 'The install profile has been written to core.extension configuration.');

    $this->rebuildContainer();
    $this->pass('Container can be rebuilt even though distribution is not written to settings.php.');
    $this->assertEqual(\Drupal::installProfile(), 'mydistro');
  }

}
