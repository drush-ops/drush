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

  protected $siteOptions = array();

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
      //'legacy-db-url' => 'mysql://root:@localhost/db',
      'legacy-db-url' => $this->db_url($site),
    );
  }

  /**
   * Test that not existent migrations are reported.
   */
  public function testNonExistentMigration() {
    $manifest = $this->webroot() . '/manifest.yml';
    $yaml = "- non_existent_migration";
    file_put_contents($manifest, $yaml);
    $this->drush('migrate-manifest', array($manifest), $this->migrateOptions);
    $output = $this->getErrorOutput();
    $this->assertContains('The following migrations were not found: non_existent_migration', $output);

  }

  /**
   * Test invalid yaml files are detected.
   */
  public function testInvalidYamlFile() {
    $invalid_manifest_file = $this->webroot() . '/invalid_manifest.yml';
    $invalid_yml = '--- :d6_migration';
    file_put_contents($invalid_manifest_file, $invalid_yml);
    $this->drushExpectError(array($invalid_manifest_file));
    $this->assertContains('The manifest file cannot be parsed.', $this->getErrorOutput());
  }

  /**
   * Test with a non-existed manifest files.
   */
  public function testNonExistentFile() {
    $this->drushExpectError(array('/some/file/that/doesnt/exist'));
    $this->assertContains('The manifest file does not exist.', $this->getErrorOutput());
  }

  /**
   * Call the drush command and expect a failure.
   *
   * @param $args
   *   An array of command arguments.
   */
  protected function drushExpectError($args) {
    // We don't need an assertion because this just errors out if we don't get
    // the expected exit status.
    $this->drush('migrate-manifest', $args, $this->migrateOptions, NULL, NULL, self::EXIT_ERROR);
  }

}
