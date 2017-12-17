<?php

/**
 * @file
 * Contains \Drupal\woot\WootManager.
 */

namespace Drupal\woot;

use Drupal\Component\Graph\Graph;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\rest\LinkManager\LinkManagerInterface;
use Drupal\rest\Plugin\Type\ResourcePluginManager;
use Symfony\Component\Serializer\Serializer;

/**
 * A simulated service for wooting.
 * @todo throw useful exceptions
 */
class WootManager
{
  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
    protected $currentUser;

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
   * @command woof
   * @aliases wf
   */
    public function woof()
    {
        return 'Woof!';
    }
}
