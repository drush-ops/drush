<?php

namespace Drupal\FunctionalTests\Installer;

use Drupal\Core\Serialization\Yaml;

/**
 * Tests distribution profile support.
 *
 * @group Installer
 */
class DistributionProfileTest extends InstallerTestBase {

  /**
   * The distribution profile info.
   *
   * @var array
   */
  protected $info;

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
          'finish_url' => '/myrootuser',
        ],
      ],
    ];
    // File API functions are not available yet.
    $path = $this->siteDirectory . '/profiles/mydistro';
    mkdir($path, 0777, TRUE);
    file_put_contents("$path/mydistro.info.yml", Yaml::encode($this->info));
    file_put_contents("$path/mydistro.install", "<?php function mydistro_install() {\Drupal::service('path.alias_storage')->save('/user/1', '/myrootuser');}");
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpLanguage() {
    // Verify that the distribution name appears.
    $this->assertRaw($this->info['distribution']['name']);
    // Verify that the distribution name is used in the site title.
    $this->assertTitle('Choose language | ' . $this->info['distribution']['name']);
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
   * Confirms that the installation succeeded.
   */
  public function testInstalled() {
    $this->assertUrl('myrootuser');
    $this->assertResponse(200);
    // Confirm that we are logged-in after installation.
    $this->assertText($this->rootUser->getUsername());

    // Confirm that Drupal recognizes this distribution as the current profile.
    $this->assertEqual(\Drupal::installProfile(), 'mydistro');
    $this->assertEqual($this->config('core.extension')->get('profile'), 'mydistro', 'The install profile has been written to core.extension configuration.');
  }

}
