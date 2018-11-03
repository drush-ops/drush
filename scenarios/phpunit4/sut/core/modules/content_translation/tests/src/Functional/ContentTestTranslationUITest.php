<?php

namespace Drupal\Tests\content_translation\Functional;

/**
 * Tests the test content translation UI with the test entity.
 *
 * @group content_translation
 */
class ContentTestTranslationUITest extends ContentTranslationUITestBase {

  /**
   * {@inheritdoc}
   */
  protected $testHTMLEscapeForAllLanguages = TRUE;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['language', 'content_translation', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    // Use the entity_test_mul as this has multilingual property support.
    $this->entityTypeId = 'entity_test_mul_changed';
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  protected function getTranslatorPermissions() {
    return array_merge(parent::getTranslatorPermissions(), ['administer entity_test content', 'view test entity']);
  }

}
