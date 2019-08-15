<?php

/**
  * @file
  *   Prepare a codebase with modules in several update status and test pm-updatestatus.
  */

namespace Unish;

/**
 *  @group slow
 *  @group pm
 */
class pmUpdateStatus extends CommandUnishTestCase {

  /**
   * Setup the test environment.
   *
   * Here we assume that any Drupal core version parses data from Drupal's
   * update service the same way. We focus on testing drush functionality.
   *
   * Several drupal core versions are already tested by pmUpdateCode.
   *
   * We choose to setup a Drupal 7 environment for convenience:
   *  - It has modules in each maintenance status
   *    and they're not willing to change in short
   *  - Drupal 6 will start to be unsupported at some point
   *  - Drupal 8 still has not enough variety to cover the tests
   */
  function setUp() {
    if (UNISH_DRUPAL_MAJOR_VERSION == '6') {
      $this->markTestSkipped("pm-update* no longer supported with Drupal 6; drupal.org does not allow stable releases for Drupal 6 contrib modules.");
    }

    $sites = $this->setUpDrupal(1, TRUE, "7.30");
    $options = array(
      'root' => $this->webroot(),
      'uri' => key($sites),
      'yes' => NULL,
      'cache' => NULL,
      'skip' => NULL, // No FirePHP
      'strict' => 0,
    );

    // Prepare a list of modules with several update statuses.
    $modules_dl = array();
    $modules_en = array();
    // Update available but not a security one. Cross fingers they never release a security update.
    $modules_dl[] = 'bad_judgement-1.0-rc38';
    $modules_en[] = 'bad_judgement';
    // Old devel release with a security update available.
    $modules_dl[] = 'devel-7.x-1.0-rc1';
    $modules_en[] = 'devel';
    // Installed version not supported.
    $modules_dl[] = 'cck-2.x-dev';
    $modules_en[] = 'cck';
    // Up to date.
    $modules_dl[] = 'ctools';
    $modules_en[] = 'ctools';

    // Download and enable the modules. Additionally download a module from git, so it has no version information.
    $this->drush('pm-download', $modules_dl, $options);
    $this->drush('pm-download', array('zen'), $options + array('package-handler' => 'git_drupalorg'));
    $modules_en[] = 'zen';
    // self::EXIT_ERROR because of bad_judgement.
    $this->drush('pm-enable', $modules_en, $options, NULL, NULL, self::EXIT_ERROR);
  }

  /**
   * Test several update statuses via drupal backend.
   */
  function testUpdateStatusDrupal() {
    $this->doTest('drupal');
  }

  /**
   * Test several update statuses via drush backend.
   */
  function testUpdateStatusDrush() {
    $this->doTest('drush');
  }

  function doTest($update_backend) {

    // Test several projects with a variety of statuses.
    $options = array(
      'root' => $this->webroot(),
      'uri' => key($this->getSites()),
      'verbose' => NULL,
      'backend' => NULL,
      'update-backend' => $update_backend,
    );
    $this->drush('pm-updatestatus', array(), $options);
    $parsed = $this->parse_backend_output($this->getOutput());
    $data = $parsed['object'];

    $expected = array(
      'drupal'                  => 'SECURITY UPDATE available',
      'bad_judgement'           => 'Update available',
      'ctools'                  => 'Up to date',
      'devel'                   => 'SECURITY UPDATE available',
      'cck'                     => 'Installed version not supported',
      'zen'                     => 'Project was not packaged by drupal.org but obtained from git. You need to enable git_deploy module',
    );
    foreach ($expected as $module => $status_msg) {
      $this->assertArrayHasKey($module, $data, "$module module present in pm-updatestatus output");
      $this->assertEquals($data[$module]['status_msg'], $status_msg, "$module status is '$status_msg'");
    }


    // Test statuses when asked for specific projects and versions.
    $args = array(
      'bad_judgement-1.0-rc38',
      'ctools-0.0',
      'devel-1.5',
      'foo',
    );
    $this->drush('pm-updatestatus', $args, $options);
    $parsed = $this->parse_backend_output($this->getOutput());
    $data = $parsed['object'];

    $expected = array(
      'bad_judgement'           => 'Specified version already installed',
      'ctools'                  => 'Specified version not found',
      'devel'                   => 'Specified version available',
      'foo'                     => 'Specified project not found',
    );
    foreach ($expected as $module => $status_msg) {
      $this->assertArrayHasKey($module, $data, "$module module present in pm-updatestatus output");
      $this->assertEquals($data[$module]['status_msg'], $status_msg, "$module status is '$status_msg'");
    }
    // We don't expect any output for other projects than the provided ones.
    $not_expected = array(
      'drupal',
      'cck',
      'zen',
    );
    foreach ($not_expected as $module) {
      $this->assertArrayNotHasKey($module, $data, "$module module not present in pm-updatestatus output");
    }


    // Test --security-only.
    $this->drush('pm-updatestatus', array(), $options + array('security-only' => NULL));
    $parsed = $this->parse_backend_output($this->getOutput());
    $data = $parsed['object'];

    $expected = array(
      'drupal' => 'SECURITY UPDATE available',
      'devel'  => 'SECURITY UPDATE available',
    );
    foreach ($expected as $module => $status_msg) {
      $this->assertArrayHasKey($module, $data, "$module module present in pm-updatestatus output");
      $this->assertEquals($data[$module]['status_msg'], $status_msg, "$module status is '$status_msg'");
    }
    // We don't expect any output for projects without security updates.
    $not_expected = array(
      'bad_judgement',
      'ctools',
      'cck',
      'zen',
    );
    foreach ($not_expected as $module) {
      $this->assertArrayNotHasKey($module, $data, "$module module not present in pm-updatestatus output");
    }


    // Test --check-disabled.
    $dis_options = array(
      'root' => $this->webroot(),
      'uri' => key($this->getSites()),
      'yes' => NULL,
    );
    $this->drush('pm-disable', array('devel'), $dis_options);

    $this->drush('pm-updatestatus', array(), $options + array('check-disabled' => 1));
    $parsed = $this->parse_backend_output($this->getOutput());
    $data = $parsed['object'];
    $this->assertArrayHasKey('devel', $data, "devel module present in pm-updatestatus output");

    $this->drush('pm-updatestatus', array(), $options + array('check-disabled' => 0));
    $parsed = $this->parse_backend_output($this->getOutput());
    $data = $parsed['object'];
    $this->assertArrayNotHasKey('devel', $data, "devel module not present in pm-updatestatus output");
  }
}

