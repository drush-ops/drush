<?php

namespace Drupal\devel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for switch to another user account.
 */
class SwitchUserController extends ControllerBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The user storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userStorage;

  /**
   * The session manager service.
   *
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  protected $sessionManager;

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * Constructs a new SwitchUserController object
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   * @param \Drupal\Core\Entity\EntityStorageInterface $user_storage
   *   The user storage.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The user storage.
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   *   The session manager service.
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   The session.
   */
  public function __construct(AccountProxyInterface $account, EntityStorageInterface $user_storage, ModuleHandlerInterface $module_handler, SessionManagerInterface $session_manager, Session $session) {
    $this->account = $account;
    $this->userStorage = $user_storage;
    $this->moduleHandler = $module_handler;
    $this->sessionManager = $session_manager;
    $this->session = $session;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity.manager')->getStorage('user'),
      $container->get('module_handler'),
      $container->get('session_manager'),
      $container->get('session')
    );
  }

  /**
   * Switches to a different user.
   *
   * We don't call session_save_session() because we really want to change users.
   * Usually unsafe!
   *
   * @param string $name
   *   The username to switch to, or NULL to log out.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function switchUser($name = NULL) {
    if (empty($name) || !($account = $this->userStorage->loadByProperties(['name' => $name]))) {
      throw new AccessDeniedHttpException();
    }
    $account = reset($account);

    // Call logout hooks when switching from original user.
    $this->moduleHandler->invokeAll('user_logout', [$this->account]);

    // Regenerate the session ID to prevent against session fixation attacks.
    $this->sessionManager->regenerate();

    // Based off masquarade module as:
    // https://www.drupal.org/node/218104 doesn't stick and instead only
    // keeps context until redirect.
    $this->account->setAccount($account);
    $this->session->set('uid', $account->id());

    // Call all login hooks when switching to masquerading user.
    $this->moduleHandler->invokeAll('user_login', [$account]);

    return $this->redirect('<front>');
  }

}
