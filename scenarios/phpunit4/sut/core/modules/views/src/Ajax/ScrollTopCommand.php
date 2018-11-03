<?php

namespace Drupal\views\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Provides an AJAX command for scrolling to the top of an element.
 *
 * This command is implemented in Drupal.AjaxCommands.prototype.viewsScrollTop.
 */
class ScrollTopCommand implements CommandInterface {

  /**
   * A CSS selector string.
   *
   * @var string
   */
  protected $selector;

  /**
   * Constructs a \Drupal\views\Ajax\ScrollTopCommand object.
   *
   * @param string $selector
   *   A CSS selector.
   */
  public function __construct($selector) {
    $this->selector = $selector;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'viewsScrollTop',
      'selector' => $this->selector,
    ];
  }

}
