<?php

namespace Drupal\Tests\taxonomy\Functional\Hal;

use Drupal\Tests\rest\Functional\BasicAuthResourceTestTrait;
use Drupal\Tests\taxonomy\Functional\Rest\VocabularyResourceTestBase;

/**
 * @group hal
 */
class VocabularyHalJsonBasicAuthTest extends VocabularyResourceTestBase {

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
