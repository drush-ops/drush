<?php

namespace Drupal\Tests\block_content\Functional\Hal;

use Drupal\Tests\block_content\Functional\Rest\BlockContentTypeResourceTestBase;
use Drupal\Tests\rest\Functional\AnonResourceTestTrait;

/**
 * @group hal
 */
class BlockContentTypeHalJsonAnonTest extends BlockContentTypeResourceTestBase {

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
