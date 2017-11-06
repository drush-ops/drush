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
    // Test the cache get command.
    $this->drush('cache-get', array('system.date', 'config'), array('format' => 'json'));
    $schema = $this->getOutputFromJSON('data');
    $this->assertNotEmpty($schema);

    // Test that get-ing a non-existant cid fails.
    $this->drush('cache-get', array('test-failure-cid'), array('format' => 'json'), NULL, NULL, self::EXIT_ERROR);
  }

  function testCacheSet() {
    // Test setting a new cache item.
    $expected = 'cache test string';
    $this->drush('cache-set', array('cache-test-cid', $expected));
    $this->drush('cache-get', array('cache-test-cid'), array('format' => 'json'));
    $data = $this->getOutputFromJSON('data');
    $this->assertEquals($expected, $data);

    // Test cache-set using all arguments and many options.
    $expected = array('key' => 'value');
    $input = array('data'=> $expected);
    $stdin = json_encode($input);
    $bin = 'default';
    $exec = sprintf('%s cache-set %s %s my_cache_id - %s CACHE_PERMANENT --input-format=json --cache-get 2>%s', self::getDrush(), "--root=" . self::escapeshellarg($this->webroot()), '--uri=' . $this->getUri(), $bin, $this->bit_bucket());
    $return = $this->execute($exec, self::EXIT_SUCCESS, NULL, [], $stdin);
    $this->drush('cache-get', array('my_cache_id'), array('format' => 'json'));
    $data = $this->getOutputFromJSON('data');
    $this->assertEquals((object)$expected, $data);
  }

  function testCacheRebuild() {
    // Test cache-clear all and cache-rebuild (D8+).
    $this->drush('cache-rebuild');
    $this->drush('cache-get', array('cache-test-cid'), ['format' => 'json'], NULL, NULL, self::EXIT_ERROR);
  }
}
