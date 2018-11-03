<?php

namespace Drupal\Tests\tour\Functional\Hal;

use Drupal\Tests\rest\Functional\CookieResourceTestTrait;
use Drupal\Tests\tour\Functional\Rest\TourResourceTestBase;

/**
 * @group hal
 */
class TourHalJsonCookieTest extends TourResourceTestBase {

  use CookieResourceTestTrait;

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

  /**
   * {@inheritdoc}
   */
  protected static $auth = 'cookie';

}
