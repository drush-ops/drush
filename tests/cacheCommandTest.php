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

    // Test cache-set using all arguments and many options.
    $expected = array('key' => 'value');
    $input = array('data'=> $expected);
    $stdin = json_encode($input);
    $exec = sprintf('echo %s | %s cache-set %s %s my_cache_id - cache CACHE_PERMANENT --format=json --cache-get 2>/dev/null', self::escapeshellarg($stdin), UNISH_DRUSH, "--root=" . self::escapeshellarg($options['root']), '--uri=' . $options['uri']);
    $return = $this->execute($exec);
    $this->drush('cache-get', array('my_cache_id', 'cache'), $options + array('format' => 'json'));
    $data = $this->getOutputFromJSON('data');
    $this->assertEquals((object)$expected, $data);

    // Test cache-clear all.
    if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
      $this->drush('cache-rebuild', array(), $options);
    }
    else {
      $this->drush('cache-clear', array('all'), $options);
    }
    $this->drush('cache-get', array('cache-test-cid'), $options + array('format' => 'json'), NULL, NULL, self::EXIT_ERROR);
  }
}
