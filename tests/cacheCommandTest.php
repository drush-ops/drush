<?php

namespace Unish;

/**
  * Cache command testing.
  *
  * @group base
  */
class cacheCommandCase extends CommandUnishTestCase {

  function setUp() {
    if (!$this->getSites()) {
      $this->setUpDrupal(1, TRUE);
    }
  }

  function testCacheGet() {
    $options = $this->getOptions();
    // Test the cache get command.
    $inputs = array(
      6 => array('variables', NULL),
      7 => array('schema', NULL),
      8 => array('system.date', 'config'),
    );
    list($key, $bin) = $inputs[UNISH_DRUPAL_MAJOR_VERSION];
    $this->drush('cache-get', array($key, $bin), $options + array('format' => 'json'));
    $schema = $this->getOutputFromJSON('data');
    $this->assertNotEmpty($schema);

    // Test that get-ing a non-existant cid fails.
    $this->drush('cache-get', array('test-failure-cid'), $options + array('format' => 'json'), NULL, NULL, self::EXIT_ERROR);
  }

  function testCacheSet() {
    $options = $this->getOptions();
    // Test setting a new cache item.
    $expected = 'cache test string';
    $this->drush('cache-set', array('cache-test-cid', $expected), $options);
    $this->drush('cache-get', array('cache-test-cid'), $options + array('format' => 'json'));
    $data = $this->getOutputFromJSON('data');
    $this->assertEquals($expected, $data);

    // Test cache-set using all arguments and many options.
    $expected = array('key' => 'value');
    $input = array('data'=> $expected);
    $stdin = json_encode($input);
    $bin = UNISH_DRUPAL_MAJOR_VERSION >= 8 ? 'default' : 'cache';
    $exec = sprintf('echo %s | %s cache-set %s %s my_cache_id - %s CACHE_PERMANENT --format=json --cache-get 2>/dev/null', self::escapeshellarg($stdin), UNISH_DRUSH, "--root=" . self::escapeshellarg($options['root']), '--uri=' . $options['uri'], $bin);
    $return = $this->execute($exec);
    $this->drush('cache-get', array('my_cache_id'), $options + array('format' => 'json'));
    $data = $this->getOutputFromJSON('data');
    $this->assertEquals((object)$expected, $data);
  }

  function testCacheRebuild() {
    $options = $this->getOptions();
    // Test cache-clear all and cache-rebuild (D8+).
    if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
      $this->drush('cache-rebuild', array(), $options);
    }
    else {
      $this->drush('cache-clear', array('all'), $options);
    }
    $this->drush('cache-get', array('cache-test-cid'), $options + array('format' => 'json'), NULL, NULL, self::EXIT_ERROR);
  }

  function getOptions() {
    return array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($this->getSites()),
    );
  }
}
