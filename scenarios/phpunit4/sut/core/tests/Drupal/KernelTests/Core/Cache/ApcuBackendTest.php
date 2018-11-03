<?php

namespace Drupal\KernelTests\Core\Cache;

use Drupal\Core\Cache\Apcu4Backend;
use Drupal\Core\Cache\ApcuBackend;

/**
 * Tests the APCu cache backend.
 *
 * @group Cache
 * @requires extension apcu
 */
class ApcuBackendTest extends GenericCacheBackendUnitTestBase {

  /**
   * Get a list of failed requirements.
   *
   * This specifically bypasses checkRequirements because it fails tests. PHP 7
   * does not have APCu and simpletest does not have a explicit "skip"
   * functionality so to emulate it we override all test methods and explicitly
   * pass when  requirements are not met.
   *
   * @return array
   */
  protected function getRequirements() {
    $requirements = [];
    if (!extension_loaded('apcu')) {
      $requirements[] = 'APCu extension not found.';
    }
    else {
      if (PHP_SAPI === 'cli' && !ini_get('apc.enable_cli')) {
        $requirements[] = 'apc.enable_cli must be enabled to run this test.';
      }
    }
    return $requirements;
  }

  /**
   * Check if requirements fail.
   *
   * If the requirements fail the test method should return immediately instead
   * of running any tests. Messages will be output to display why the test was
   * skipped.
   */
  protected function requirementsFail() {
    $requirements = $this->getRequirements();
    if (!empty($requirements)) {
      foreach ($requirements as $message) {
        $this->pass($message);
      }
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function createCacheBackend($bin) {
    if (version_compare(phpversion('apcu'), '5.0.0', '>=')) {
      return new ApcuBackend($bin, $this->databasePrefix, \Drupal::service('cache_tags.invalidator.checksum'));
    }
    else {
      return new Apcu4Backend($bin, $this->databasePrefix, \Drupal::service('cache_tags.invalidator.checksum'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    foreach ($this->cachebackends as $bin => $cachebackend) {
      $this->cachebackends[$bin]->removeBin();
    }
    parent::tearDown();
  }

  /**
   * {@inheritdoc}
   */
  public function testSetGet() {
    if ($this->requirementsFail()) {
      return;
    }
    parent::testSetGet();

    // Make sure entries are permanent (i.e. no TTL).
    $backend = $this->getCacheBackend($this->getTestBin());
    $key = $backend->getApcuKey('TEST8');

    if (class_exists('\APCUIterator')) {
      $iterator = new \APCUIterator('/^' . $key . '/');
    }
    else {
      $iterator = new \APCIterator('user', '/^' . $key . '/');
    }

    foreach ($iterator as $item) {
      $this->assertEqual(0, $item['ttl']);
      $found = TRUE;
    }
    $this->assertTrue($found);
  }

  /**
   * {@inheritdoc}
   */
  public function testDelete() {
    if ($this->requirementsFail()) {
      return;
    }
    parent::testDelete();
  }

  /**
   * {@inheritdoc}
   */
  public function testValueTypeIsKept() {
    if ($this->requirementsFail()) {
      return;
    }
    parent::testValueTypeIsKept();
  }

  /**
   * {@inheritdoc}
   */
  public function testGetMultiple() {
    if ($this->requirementsFail()) {
      return;
    }
    parent::testGetMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public function testSetMultiple() {
    if ($this->requirementsFail()) {
      return;
    }
    parent::testSetMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public function testDeleteMultiple() {
    if ($this->requirementsFail()) {
      return;
    }
    parent::testDeleteMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public function testDeleteAll() {
    if ($this->requirementsFail()) {
      return;
    }
    parent::testDeleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function testInvalidate() {
    if ($this->requirementsFail()) {
      return;
    }
    parent::testInvalidate();
  }

  /**
   * {@inheritdoc}
   */
  public function testInvalidateTags() {
    if ($this->requirementsFail()) {
      return;
    }
    parent::testInvalidateTags();
  }

  /**
   * {@inheritdoc}
   */
  public function testInvalidateAll() {
    if ($this->requirementsFail()) {
      return;
    }
    parent::testInvalidateAll();
  }

  /**
   * {@inheritdoc}
   */
  public function testRemoveBin() {
    if ($this->requirementsFail()) {
      return;
    }
    parent::testRemoveBin();
  }

}
