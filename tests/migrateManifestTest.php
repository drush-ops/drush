<?php

/**
 * @file
 * Migrate related tests.
 */

namespace Unish;

/**
 * @group commands
 */
class migrateManifestTest extends CommandUnishTestCase {

  /**
   * The site options to be used when running commands against Drupal.
   *
   * @var array
   */
  protected $siteOptions = array();

  /**
   * Migrate specific options when running commands against Drupal.
   *
   * @var array
   */
  protected $migrateOptions = array();

  /**
   * {@inheritdoc}
   */
  public function setUp() {

    if (UNISH_DRUPAL_MAJOR_VERSION < 8) {
      $this->markTestSkipped('Migrate manifest is for D8');
    }

    if (!$sites = $this->getSites()) {
      $sites = $this->setUpDrupal(1, TRUE, UNISH_DRUPAL_MAJOR_VERSION, 'standard');
    }
    $site = key($sites);
    $root = $this->webroot();
    $this->siteOptions = array(
      'root' => $root,
      'uri' => $site,
      'yes' => NULL,
    );
    $this->drush('pm-enable', array('migrate_drupal'), $this->siteOptions);

    // All migrate commands will need this option.
    $this->migrateOptions = $this->siteOptions + array(
      'legacy-db-url' => $this->db_url($site),
      'simulate' => NULL,
      'backend' => NULL,
    );
  }

  /**
   * Test that simple migration works.
   */
  public function testSimpleMigration() {
    $manifest = $this->createManifestFile('- d6_action_settings');
    $return = $this->drushExpectSuccess(array($manifest));
    $this->assertArrayHasKey('d6_action_settings', $return['object']);
  }

  /**
   * Test multiple migrations that have config.
   */
  public function testMigrationWithConfig() {
    $yaml = "- d6_file:
  source:
    conf_path: sites/assets
  destination:
    source_base_path: destination/base/path
    destination_path_property: uri
- d6_action_settings";
    $manifest = $this->createManifestFile($yaml);
    $return = $this->drushExpectSuccess(array($manifest));

    $this->assertArrayHasKey('d6_file', $return['object']);
    $this->assertArrayHasKey('d6_action_settings', $return['object']);

    // Check source config.
    $source_config = $return['object']['d6_file']['source'];
    $this->assertEquals('sites/assets', $source_config['conf_path']);

    // Check destination config.
    $destination_config = $return['object']['d6_file']['destination'];
    $this->assertEquals('destination/base/path', $destination_config['source_base_path']);
    $this->assertEquals('uri', $destination_config['destination_path_property']);
  }

  /**
   * Test that not existent migrations are reported.
   */
  public function testNonExistentMigration() {
    $manifest = $this->createManifestFile('- non_existent_migration');
    $return = $this->drushExpectSuccess(array($manifest));
    $this->assertArrayNotHasKey('non_existent_migration', $return['object']);
  }

  /**
   * Test invalid Yaml files are detected.
   */
  public function testInvalidYamlFile() {
    $invalid_yml = '--- :d6_migration';
    $manifest = $this->createManifestFile($invalid_yml);
    $return = $this->drushExpectError(array($manifest));
    $this->assertContains('The manifest file cannot be parsed.', $return['error_log']['MIGRATE_ERROR'][0]);
  }

  /**
   * Test with a non-existed manifest files.
   */
  public function testNonExistentFile() {
    $return = $this->drushExpectError(array('/some/file/that/doesnt/exist'));
    $this->assertContains('The manifest file does not exist.', $return['error_log']['MIGRATE_ERROR'][0]);
  }

  /**
   * Call the Drush command and expect a failure.
   *
   * @param $args
   *   An array of command arguments.
   *
   * @return string
   *   The error output.
   */
  protected function drushExpectError($args) {
    // We don't need an assertion because this just errors out if we don't get
    // the expected exit status.
    $this->drush('migrate-manifest', $args, $this->migrateOptions, NULL, NULL, self::EXIT_ERROR);
    return $this->parse_backend_output($this->getOutput());
  }

  /**
   * Call the drush command, expect success and redirect output to standard out.
   *
   * @param $args
   *   An array of command arguments.
   *
   * @return string
   *   The success output.
   */
  protected function drushExpectSuccess($args) {
    $this->drush('migrate-manifest', $args, $this->migrateOptions, NULL, NULL, self::EXIT_SUCCESS, '2>&1');
    return $this->parse_backend_output($this->getOutput());
  }

  /**
   * Create a manifest file in the web root with the specified migrations.
   *
   * @param string $yaml
   *   A string of yaml for the migration file.
   *
   * @return string
   *   The path to the manifest file.
   */
  protected function createManifestFile($yaml) {
    $manifest = $this->webroot() . '/manifest.yml';
    file_put_contents($manifest, $yaml);
    return $manifest;
  }

}
