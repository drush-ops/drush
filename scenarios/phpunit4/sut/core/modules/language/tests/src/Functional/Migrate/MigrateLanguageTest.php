<?php

namespace Drupal\Tests\language\Functional\Migrate;

use Drupal\language\ConfigurableLanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;

/**
 * @group migrate_drupal_6
 */
class MigrateLanguageTest extends MigrateDrupal6TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['language'];

  /**
   * Asserts various properties of a configurable language entity.
   *
   * @param string $id
   *   The language ID.
   * @param string $label
   *   The language name.
   * @param string $direction
   *   (optional) The language's direction (one of the DIRECTION_* constants in
   *   ConfigurableLanguageInterface). Defaults to LTR.
   * @param int $weight
   *   (optional) The weight of the language. Defaults to 0.
   */
  protected function assertLanguage($id, $label, $direction = ConfigurableLanguageInterface::DIRECTION_LTR, $weight = 0) {
    /** @var \Drupal\language\ConfigurableLanguageInterface $language */
    $language = ConfigurableLanguage::load($id);
    $this->assertTrue($language instanceof ConfigurableLanguageInterface);
    $this->assertIdentical($label, $language->label());
    $this->assertIdentical($direction, $language->getDirection());
    $this->assertIdentical(0, $language->getWeight());
    $this->assertFalse($language->isLocked());
  }

  /**
   * Tests migration of Drupal 6 languages to configurable language entities.
   */
  public function testLanguageMigration() {
    $this->executeMigration('language');
    $this->assertLanguage('en', 'English');
    $this->assertLanguage('fr', 'French');
  }

}
