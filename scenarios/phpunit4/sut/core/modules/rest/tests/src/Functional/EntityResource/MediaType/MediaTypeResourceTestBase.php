<?php

namespace Drupal\Tests\rest\Functional\EntityResource\MediaType;

@trigger_error('The ' . __NAMESPACE__ . '\MediaTypeResourceTestBase is deprecated in Drupal 8.6.x and will be removed before Drupal 9.0.0. Instead, use Drupal\Tests\media\Functional\Rest\MediaTypeResourceTestBase. See https://www.drupal.org/node/2971931.', E_USER_DEPRECATED);

use Drupal\Tests\media\Functional\Rest\MediaTypeResourceTestBase as MediaTypeResourceTestBaseReal;

/**
 * @deprecated in Drupal 8.6.x. Will be removed before Drupal 9.0.0. Use
 *   Drupal\Tests\media\Functional\Rest\MediaTypeResourceTestBase instead.
 *
 * @see https://www.drupal.org/node/2971931
 */
abstract class MediaTypeResourceTestBase extends MediaTypeResourceTestBaseReal {
}
