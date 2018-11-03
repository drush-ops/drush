<?php

namespace Drupal\Tests\rest\Functional\EntityResource\Action;

@trigger_error('The ' . __NAMESPACE__ . '\ActionResourceTestBase is deprecated in Drupal 8.6.x and will be removed before Drupal 9.0.0. Instead, use Drupal\Tests\system\Functional\Rest\ActionResourceTestBase. See https://www.drupal.org/node/2971931.', E_USER_DEPRECATED);

use Drupal\Tests\system\Functional\Rest\ActionResourceTestBase as ActionResourceTestBaseReal;

/**
 * @deprecated in Drupal 8.6.x. Will be removed before Drupal 9.0.0. Use
 *   Drupal\Tests\system\Functional\Rest\ActionResourceTestBase instead.
 *
 * @see https://www.drupal.org/node/2971931
 */
abstract class ActionResourceTestBase extends ActionResourceTestBaseReal {
}
