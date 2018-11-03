<?php

namespace Drupal\Tests\image\Functional\Hal;

use Drupal\Tests\image\Functional\Rest\ImageStyleResourceTestBase;
use Drupal\Tests\rest\Functional\CookieResourceTestTrait;

/**
 * @group hal
 */
class ImageStyleHalJsonCookieTest extends ImageStyleResourceTestBase {

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
