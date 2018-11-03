<?php

namespace Drupal\Tests\search\Functional\Hal;

use Drupal\Tests\rest\Functional\AnonResourceTestTrait;
use Drupal\Tests\search\Functional\Rest\SearchPageResourceTestBase;

/**
 * @group hal
 */
class SearchPageHalJsonAnonTest extends SearchPageResourceTestBase {

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
