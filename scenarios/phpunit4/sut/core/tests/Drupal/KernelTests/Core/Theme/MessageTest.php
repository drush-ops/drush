<?php

namespace Drupal\KernelTests\Core\Theme;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests built-in message theme functions.
 *
 * @group Theme
 */
class MessageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system'];

  /**
   * Tests setting messages output.
   */
  public function testMessages() {
    // Enable the Classy theme.
    \Drupal::service('theme_handler')->install(['classy']);
    $this->config('system.theme')->set('default', 'classy')->save();

    \Drupal::messenger()->addError('An error occurred');
    \Drupal::messenger()->addStatus('But then something nice happened');
    $messages = [
      '#type' => 'status_messages',
    ];
    $this->render($messages);
    $this->assertRaw('messages messages--error');
    $this->assertRaw('messages messages--status');
  }

}
