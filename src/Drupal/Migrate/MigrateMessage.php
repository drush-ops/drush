<?php

namespace Drush\Drupal\Migrate;

use Drupal\migrate\MigrateMessageInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Print message in drush from migrate message.
 */
class MigrateMessage implements MigrateMessageInterface, LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * DrushLogMigrateMessage constructor.
   */
  public function __construct(LoggerInterface $logger) {
    $this->setLogger($logger);
  }

  /**
   * Output a message from the migration.
   *
   * @param string $message
   *   The message to display.
   * @param string $type
   *   The type of message to display.
   */
  public function display($message, $type = 'status') {
    $type = ($type === 'status' ? 'notice' : $type);
    $this->logger->$type($message);
  }

}
