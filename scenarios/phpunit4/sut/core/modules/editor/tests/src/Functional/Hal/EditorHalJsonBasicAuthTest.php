<?php

namespace Drupal\Tests\editor\Functional\Hal;

use Drupal\Tests\editor\Functional\Rest\EditorResourceTestBase;
use Drupal\Tests\rest\Functional\BasicAuthResourceTestTrait;

/**
 * @group hal
 */
class EditorHalJsonBasicAuthTest extends EditorResourceTestBase {

  use BasicAuthResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['hal', 'basic_auth'];

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
  protected static $auth = 'basic_auth';

}
