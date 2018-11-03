<?php

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;

/**
 * Tests the messages area handler.
 *
 * @group views
 * @see \Drupal\views\Plugin\views\area\Messages
 */
class AreaMessagesTest extends ViewsKernelTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_area_messages'];

  /**
   * Tests the messages area handler.
   */
  public function testMessageText() {
    \Drupal::messenger()->addStatus('My drupal set message.');

    $view = Views::getView('test_area_messages');

    $view->setDisplay('default');
    $this->executeView($view);
    $output = $view->render();
    $output = \Drupal::service('renderer')->renderRoot($output);
    $this->setRawContent($output);
    $this->assertText('My drupal set message.');
  }

}
