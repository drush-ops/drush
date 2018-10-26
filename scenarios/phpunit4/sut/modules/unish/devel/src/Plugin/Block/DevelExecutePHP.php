<?php

namespace Drupal\devel\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a block for executing PHP code.
 *
 * @Block(
 *   id = "devel_execute_php",
 *   admin_label = @Translation("Execute PHP")
 * )
 */
class DevelExecutePHP extends BlockBase {

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'execute php code');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return \Drupal::formBuilder()->getForm('Drupal\devel\Form\ExecutePHP');
  }

}
