<?php

declare(strict_types=1);

namespace Drupal\woot;

use Drupal\Core\Session\AccountProxyInterface;

/**
 * A simulated service for wooting.
 */
class WootManager
{
    protected AccountProxyInterface $currentUser;

    public function __construct(AccountProxyInterface $current_user)
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
    public function woof(): string
    {
        return 'Woof!';
    }
}
