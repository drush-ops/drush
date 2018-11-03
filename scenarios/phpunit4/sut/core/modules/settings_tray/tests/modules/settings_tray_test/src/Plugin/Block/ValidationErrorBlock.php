<?php

namespace Drupal\settings_tray_test\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Block with validation error' test block.
 *
 * @Block(
 *   id = "settings_tray_test_validation",
 *   admin_label = @Translation("Block with validation error")
 * )
 */
class ValidationErrorBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return ['#markup' => '<span>If I had more time this would be very witty :(.</span>'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $form_state->setError($form['label'], 'Sorry system error. Please save again.');
  }

}
