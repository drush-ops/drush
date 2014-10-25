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
    $sites = $this->setUpDrupal(1, TRUE, 7);
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
    $modules_dl[] = 'field-conditional-state-2.x-dev';
    $modules_en[] = 'field_conditional_state';
    // Up to date.
    $modules_dl[] = 'ctools';
    $modules_en[] = 'ctools';

    // Download and enable the modules. Additionally download a module from git, so it has no version information.
    $this->drush('pm-download', $modules_dl, $options);
    $this->drush('pm-download', array('zen'), $options + array('package-handler' => 'git_drupalorg'));
    $modules_en[] = 'zen';
    // self::EXIT_ERROR because of bad_judgement.
    $this->drush('pm-enable', $modules_en, $options,NULL, NULL, self::EXIT_ERROR);
  }

  /**
   * Test several update statuses.
   */
  function testUpdateStatus() {
    $options = array(
      'root' => $this->webroot(),
      'uri' => key($this->getSites()),
      'verbose' => NULL,
      'backend' => null,
    );
    $this->drush('pm-updatestatus', array(), $options);
    $parsed = $this->parse_backend_output($this->getOutput());
    $data = $parsed['object'];

    $expected = array(
      'bad_judgement'           => 'Update available',
      'ctools'                  => 'Up to date',
      'devel'                   => 'SECURITY UPDATE available',
      'field-conditional-state' => 'Installed version not supported',
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
  }
}
