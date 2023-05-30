<?php

declare(strict_types=1);

namespace Drupal\woot;

use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;

/**
 * A simulated service for wooting.
 */
class WootManager
{
    protected AccountProxyInterface $currentUser;

    protected LoggerInterface $logger;

    public function __construct(AccountProxyInterface $current_user, LoggerInterface $logger)
    {
        $this->currentUser = $current_user;
        $this->logger = $logger;
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
        $this->logger->notice('Message3 - via wootManager::woof() using logger->notice');
        return 'Woof!';
    }
}
