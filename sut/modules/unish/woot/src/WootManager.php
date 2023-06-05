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
        \Drupal::logger('woot')->notice('Message 3 - via wootManager::woof() using \Drupal::logger(woot)->notice');
        $this->logger->notice('Message 4 - via wootManager::woof() using this->logger->notice');
        return 'Woof!';
    }
}
