<?php

namespace Drupal\block_test\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block to test caching.
 *
 * @Block(
 *   id = "test_cache",
 *   admin_label = @Translation("Test block caching")
 * )
 */
class TestCacheBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $content = \Drupal::state()->get('block_test.content');

    $build = [];
    if (!empty($content)) {
      $build['#markup'] = $content;
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return \Drupal::state()->get('block_test.cache_contexts', []);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return \Drupal::state()->get('block_test.cache_max_age', parent::getCacheMaxAge());
  }

}
