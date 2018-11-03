<?php

namespace Drupal\Core\Session;

/**
 * A proxied implementation of AccountInterface.
 *
 * The reason why we need an account proxy is that we don't want to have global
 * state directly stored in the container.
 *
 * This proxy object avoids multiple invocations of the authentication manager
 * which can happen if the current user is accessed in constructors. It also
 * allows legacy code to change the current user where the user cannot be
 * directly injected into dependent code.
 */
class AccountProxy implements AccountProxyInterface {

  /**
   * The instantiated account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Account id.
   *
   * @var int
   */
  protected $id = 0;

  /**
   * Initial account id.
   *
   * @var int
   *
   * @deprecated Scheduled for removal in Drupal 8.4.x. Use $this->id instead.
   */
  protected $initialAccountId;

  /**
   * {@inheritdoc}
   */
  public function setAccount(AccountInterface $account) {
    // If the passed account is already proxied, use the actual account instead
    // to prevent loops.
    if ($account instanceof static) {
      $account = $account->getAccount();
    }
    $this->account = $account;
    $this->id = $account->id();
    date_default_timezone_set(drupal_get_user_timezone());
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount() {
    if (!isset($this->account)) {
      if ($this->id) {
        // After the container is rebuilt, DrupalKernel sets the initial
        // account to the id of the logged in user. This is necessary in order
        // to refresh the user account reference here.
        $this->setAccount($this->loadUserEntity($this->id));
      }
      else {
        $this->account = new AnonymousUserSession();
      }
    }

    return $this->account;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoles($exclude_locked_roles = FALSE) {
    return $this->getAccount()->getRoles($exclude_locked_roles);
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermission($permission) {
    return $this->getAccount()->hasPermission($permission);
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthenticated() {
    return $this->getAccount()->isAuthenticated();
  }

  /**
   * {@inheritdoc}
   */
  public function isAnonymous() {
    return $this->getAccount()->isAnonymous();
  }

  /**
   * {@inheritdoc}
   */
  public function getPreferredLangcode($fallback_to_default = TRUE) {
    return $this->getAccount()->getPreferredLangcode($fallback_to_default);
  }

  /**
   * {@inheritdoc}
   */
  public function getPreferredAdminLangcode($fallback_to_default = TRUE) {
    return $this->getAccount()->getPreferredAdminLangcode($fallback_to_default);
  }

  /**
   * {@inheritdoc}
   */
  public function getUsername() {
    return $this->getAccountName();
  }

  /**
   * {@inheritdoc}
   */
  public function getAccountName() {
    return $this->getAccount()->getAccountName();
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName() {
    return $this->getAccount()->getDisplayName();
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail() {
    return $this->getAccount()->getEmail();
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeZone() {
    return $this->getAccount()->getTimeZone();
  }

  /**
   * {@inheritdoc}
   */
  public function getLastAccessedTime() {
    return $this->getAccount()->getLastAccessedTime();
  }

  /**
   * {@inheritdoc}
   */
  public function setInitialAccountId($account_id) {
    if (isset($this->account)) {
      throw new \LogicException('AccountProxyInterface::setInitialAccountId() cannot be called after an account was set on the AccountProxy');
    }

    $this->id = $this->initialAccountId = $account_id;
  }

  /**
   * Load a user entity.
   *
   * The entity manager requires additional initialization code and cache
   * clearing after the list of modules is changed. Therefore it is necessary to
   * retrieve it as late as possible.
   *
   * Because of serialization issues it is currently not possible to inject the
   * container into the AccountProxy. Thus it is necessary to retrieve the
   * entity manager statically.
   *
   * @see https://www.drupal.org/node/2430447
   *
   * @param int $account_id
   *   The id of an account to load.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   An account or NULL if none is found.
   */
  protected function loadUserEntity($account_id) {
    return \Drupal::entityManager()->getStorage('user')->load($account_id);
  }

}
