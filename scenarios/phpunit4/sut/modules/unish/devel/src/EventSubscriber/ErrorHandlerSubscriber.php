<?php

namespace Drupal\devel\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listener for handling PHP errors.
 */
class ErrorHandlerSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * ErrorHandlerSubscriber constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   */
  public function __construct(AccountProxyInterface $account) {
    $this->account = $account;
  }

  /**
   * Register devel error handler.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The event to process.
   */
  public function registerErrorHandler(Event $event = NULL) {
    if ($this->account && $this->account->hasPermission('access devel information')) {
      devel_set_handler(devel_get_handlers());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Runs as soon as possible in the request but after
    // AuthenticationSubscriber (priority 300) because you need to access to
    // the current user for determine whether register the devel error handler
    // or not.
    $events[KernelEvents::REQUEST][] = ['registerErrorHandler', 256];

    return $events;
  }

}
