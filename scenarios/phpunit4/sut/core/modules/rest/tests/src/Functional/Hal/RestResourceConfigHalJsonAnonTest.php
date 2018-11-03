<?php

namespace Drupal\Tests\rest\Functional\Hal;

use Drupal\Tests\rest\Functional\AnonResourceTestTrait;
use Drupal\Tests\rest\Functional\Rest\RestResourceConfigResourceTestBase;

/**
 * @group hal
 */
class RestResourceConfigHalJsonAnonTest extends RestResourceConfigResourceTestBase {

  use AnonResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['hal'];

  /**
   * {@inheritdoc}
   */
  protected static $format = 'hal_json';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/hal+json';

}
