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

    $sites = $this->setUpDrupal(1, TRUE, UNISH_DRUPAL_MAJOR_VERSION, 'standard');
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
    );
  }

  /**
   * Test that simple migration works.
   */
  public function testSimpleMigration() {
    $manifest = $this->createManifestFile('- d6_action_settings');
    $this->drushExpectSuccess(array($manifest));
    $this->assertContains('Importing: d6_action_settings', $this->getOutput(), 'Found migration');
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
    $output = $this->drushExpectSuccess(array($manifest));

    $this->assertContains('Importing: d6_file', $output);
    $this->assertContains('[conf_path] => sites/assets', $output);
    $this->assertContains('[source_base_path] => destination/base/path', $output);
    $this->assertContains('[destination_path_property] => uri', $output);
    $this->assertContains('Importing: d6_action_settings', $output);
  }

  /**
   * Test that not existent migrations are reported.
   */
  public function testNonExistentMigration() {
    $manifest = $this->createManifestFile('- non_existent_migration');
    $output = $this->drushExpectSuccess(array($manifest));
    $this->assertContains('The following migrations were not found: non_existent_migration', $output);

  }

  /**
   * Test invalid Yaml files are detected.
   */
  public function testInvalidYamlFile() {
    $invalid_yml = '--- :d6_migration';
    $manifest = $this->createManifestFile($invalid_yml);
    $output = $this->drushExpectError(array($manifest));
    $this->assertContains('The manifest file cannot be parsed.', $output);
  }

  /**
   * Test with a non-existed manifest files.
   */
  public function testNonExistentFile() {
    $output = $this->drushExpectError(array('/some/file/that/doesnt/exist'));
    $this->assertContains('The manifest file does not exist.', $output);
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
    return $this->getErrorOutput();
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
    return $this->getOutput();
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
