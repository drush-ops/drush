<?php

namespace Unish;

/**
  * Cache command testing.
  *
  * @group base
  */
class CacheCommandCase extends CommandUnishTestCase {

    public function setUp()
    {
        if (!$this->getSites()) {
            $this->setUpDrupal(1, true);
        }
    }

    public function testCacheGet()
    {
        // Test the cache get command.
        $this->drush('cache-get', ['system.date', 'config'], ['format' => 'json']);
        $schema = $this->getOutputFromJSON('data');
        $this->assertNotEmpty($schema);

        // Test that get-ing a non-existant cid fails.
        $this->drush('cache-get', ['test-failure-cid'], ['format' => 'json'], null, null, self::EXIT_ERROR);
    }

    public function testCacheSet()
    {
        // Test setting a new cache item.
        $expected = 'cache test string';
        $this->drush('cache-set', ['cache-test-cid', $expected]);
        $this->drush('cache-get', ['cache-test-cid'], ['format' => 'json']);
        $data = $this->getOutputFromJSON('data');
        $this->assertEquals($expected, $data);

        // Test cache-set using all arguments and many options.
        $expected = ['key' => 'value'];
        $input = ['data'=> $expected];
        $stdin = json_encode($input);
        $bin = 'default';
        $exec = sprintf('%s cache-set %s %s my_cache_id - %s CACHE_PERMANENT --input-format=json --cache-get 2>%s', self::getDrush(), "--root=" . self::escapeshellarg($this->webroot()), '--uri=' . $this->getUri(), $bin, $this->bitBucket());
        $return = $this->execute($exec, self::EXIT_SUCCESS, null, [], $stdin);
        $this->drush('cache-get', ['my_cache_id'], ['format' => 'json']);
        $data = $this->getOutputFromJSON('data');
        $this->assertEquals((object)$expected, $data);
    }

    public function testCacheRebuild()
    {
        // Test cache-clear all and cache-rebuild (D8+).
        $this->drush('cache-rebuild');
        $this->drush('cache-get', ['cache-test-cid'], ['format' => 'json'], null, null, self::EXIT_ERROR);
    }
}
