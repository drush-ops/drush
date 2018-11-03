<?php

namespace Drupal\Component\Plugin\Exception;

/**
 * Generic Plugin exception class to be thrown when no more specific class
 * is applicable.
 */
class PluginException extends \Exception implements ExceptionInterface {}
