<?php

namespace Drupal\Tests\language\Functional\Hal;

use Drupal\Tests\language\Functional\Rest\ConfigurableLanguageResourceTestBase;
use Drupal\Tests\rest\Functional\BasicAuthResourceWithInterfaceTranslationTestTrait;

/**
 * @group hal
 */
class ConfigurableLanguageHalJsonBasicAuthTest extends ConfigurableLanguageResourceTestBase {

  use BasicAuthResourceWithInterfaceTranslationTestTrait;

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
