<?php

/**
  * @file
  *   Unit tests for sitealias.inc
  *
  * @group base
  */
class saUnitCase extends Drush_UnitTestCase {

  /**
   * Tests _sitealias_array_merge().
   *
   * @see _sitealias_array_merge().
   */
  public function testArrayMerge() {
    // Original site alias.
    $site_alias_a = array(
      'remote-host' => 'fake.remote-host.com',
      'remote-user' => 'www-admin',
      'root' => '/fake/path/to/root',
      'uri' => 'default',
      'command-specific' => array(
        'rsync' => array(
          'delete' => TRUE,
        ),
      ),
    );
    // Site alias which overrides some settings from $site_alias_a.
    $site_alias_b = array(
      'remote-host' => 'another-fake.remote-host.com',
      'remote-user' => 'www-other',
      'root' => '/fake/path/to/root',
      'uri' => 'default',
      'command-specific' => array(
        'rsync' => array(
          'delete' => FALSE,
        ),
      ),
    );
    // Expected result from merging $site_alias_a and $site_alias_b.
    $site_alias_expected = array(
      'remote-host' => 'another-fake.remote-host.com',
      'remote-user' => 'www-other',
      'root' => '/fake/path/to/root',
      'uri' => 'default',
      'command-specific' => array(
        'rsync' => array(
          'delete' => FALSE,
        ),
      ),
    );

    $site_alias_result = _sitealias_array_merge($site_alias_a, $site_alias_b);
    $this->assertEquals($site_alias_expected, $site_alias_result);
  }
}
