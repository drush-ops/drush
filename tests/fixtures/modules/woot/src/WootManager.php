<?php

declare(strict_types=1);

namespace Drupal\woot;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\rest\LinkManager\LinkManagerInterface;

/**
 * A simulated service for wooting.
 * @todo throw useful exceptions
 */
class WootManager
{
    protected AccountInterface $currentUser;

  /**
   * Constructs the default content manager.
   *
   * @param \Drupal\Core\Session|AccountInterface $current_user
   *   The current user.
   */
    public function __construct(AccountInterface $current_user)
    {
        $this->currentUser = $current_user;
    }

  /**
   * Woof mightily. Note that we can include commands directly
   * inside a service class.
   *
   * @command woot:woof
   * @aliases wf
   */
    public function woof()
    {
        return 'Woof!';
    }
}
