<?php

namespace Drupal\Core\Config;

/**
 * Exception throw when an immutable config object is altered.
 *
 * @see \Drupal\Core\Config\ImmutableConfig
 */
class ImmutableConfigException extends \LogicException {
}
