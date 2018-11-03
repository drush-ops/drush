<?php

namespace Drupal\Tests\workflows\Functional\Hal;

use Drupal\Tests\rest\Functional\AnonResourceTestTrait;
use Drupal\Tests\workflows\Functional\Rest\WorkflowResourceTestBase;

/**
 * @group hal
 */
class WorkflowHalJsonAnonTest extends WorkflowResourceTestBase {

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
