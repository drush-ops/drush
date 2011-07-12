<?php

/**
  * cache command testing.
  */
class cacheCommandCase extends Drush_CommandTestCase {
  public function testCacheGetSetClear() {
    $this->setUpDrupal(1, TRUE);
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($this->sites),
    );

    // Test the cache get command.
    $this->drush('cache-get', array('schema'), $options + array('format' => 'json'));
    $schema = json_decode($this->getOutput());
    $this->assertObjectHasAttribute('data', $schema);

    // Test that get-ing a non-existant cid fails.
    $this->drush('cache-get', array('test-failure-cid'), $options + array('format' => 'json'));
    $output = json_decode($this->getOutput());
    $this->assertEmpty($output);

    // Test setting a new cache item.
    $cache_test_value = 'cache test string';
    $this->drush('cache-set', array('cache-test-cid', $cache_test_value), $options);
    $this->drush('cache-get', array('cache-test-cid'), $options + array('format' => 'json'));
    $cache_value = json_decode($this->getOutput());
    $this->assertEquals($cache_test_value, $cache_value->data);

    // Test cache-clear all.
    $this->drush('cache-clear', array('all'), $options);
    $this->drush('cache-get', array('cache-test-cid'), $options + array('format' => 'json'));
    $output = json_decode($this->getOutput());
    $this->assertEmpty($output);
  }
}
