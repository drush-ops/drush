<?php

namespace Drupal\Core\Session;

use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

/**
 * Defines the session manager interface.
 */
interface SessionManagerInterface extends SessionStorageInterface {

  /**
   * Ends a specific user's session(s).
   *
   * @param int $uid
   *   User ID.
   */
  public function delete($uid);

  /**
   * Destroys the current session and removes session cookies.
   */
  public function destroy();

  /**
   * Sets the write safe session handler.
   *
   * @todo: This should be removed once all database queries are removed from
   *   the session manager class.
   *
   * @var \Drupal\Core\Session\WriteSafeSessionHandlerInterface
   */
  public function setWriteSafeHandler(WriteSafeSessionHandlerInterface $handler);

}
