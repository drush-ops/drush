<?php

namespace Drupal\node\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a 'Syndicate' block that links to the site's RSS feed.
 *
 * @Block(
 *   id = "node_syndicate_block",
 *   admin_label = @Translation("Syndicate"),
 *   category = @Translation("System")
 * )
 */
class SyndicateBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'block_count' => 10,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'feed_icon',
      '#url' => 'rss.xml',
    ];
  }

}
