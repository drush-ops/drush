<?php

/**
  * cache command testing.
  *
  * @group base
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
    $key = UNISH_DRUPAL_MAJOR_VERSION == 6 ? 'variables' : 'schema';
    $this->drush('cache-get', array($key), $options + array('format' => 'json'));
    $schema = $this->getOutputFromJSON('data');
    $this->assertNotEmpty($schema);

    // Test that get-ing a non-existant cid fails.
    $this->drush('cache-get', array('test-failure-cid'), $options + array('format' => 'json'), NULL, NULL, self::EXIT_ERROR);

    // Test setting a new cache item.
    $expected = 'cache test string';
    $this->drush('cache-set', array('cache-test-cid', $expected), $options);
    $this->drush('cache-get', array('cache-test-cid'), $options + array('format' => 'json'));
    $data = $this->getOutputFromJSON('data');
    $this->assertEquals($expected, $data);

    // Test cache-clear all.
    $this->drush('cache-clear', array('all'), $options);
    $this->drush('cache-get', array('cache-test-cid'), $options + array('format' => 'json'), NULL, NULL, self::EXIT_ERROR);
  }
}
