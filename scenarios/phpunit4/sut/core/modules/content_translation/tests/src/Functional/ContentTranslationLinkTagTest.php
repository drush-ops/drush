<?php

namespace Drupal\Tests\content_translation\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\content_translation_test\Entity\EntityTestTranslatableNoUISkip;

/**
 * Tests whether canonical link tags are present for content entities.
 *
 * @group content_translation
 */
class ContentTranslationLinkTagTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['entity_test', 'content_translation', 'content_translation_test', 'language'];

  /**
   * The added languages.
   *
   * @var string[]
   */
  protected $langcodes;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Set up user.
    $user = $this->drupalCreateUser([
      'view test entity',
      'view test entity translations',
      'administer entity_test content',
    ]);
    $this->drupalLogin($user);

    // Add additional languages.
    $this->langcodes = ['it', 'fr'];
    foreach ($this->langcodes as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }

    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();
  }

  /**
   * Create a test entity with translations.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   An entity with translations.
   */
  protected function createTranslatableEntity() {
    $entity = EntityTestMul::create(['label' => $this->randomString()]);

    // Create translations for non default languages.
    foreach ($this->langcodes as $langcode) {
      $entity->addTranslation($langcode, ['label' => $this->randomString()]);
    }
    $entity->save();

    return $entity;
  }

  /**
   * Tests alternate link tag found for entity types with canonical links.
   */
  public function testCanonicalAlternateTags() {
    /** @var \Drupal\Core\Language\LanguageManagerInterface $languageManager */
    $languageManager = $this->container->get('language_manager');
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
    $entityTypeManager = $this->container->get('entity_type.manager');

    $definition = $entityTypeManager->getDefinition('entity_test_mul');
    $this->assertTrue($definition->hasLinkTemplate('canonical'), 'Canonical link template found for entity_test.');

    $entity = $this->createTranslatableEntity();
    $url_base = $entity->toUrl('canonical')
      ->setAbsolute();

    $langcodes_all = $this->langcodes;
    $langcodes_all[] = $languageManager
      ->getDefaultLanguage()
      ->getId();

    /** @var \Drupal\Core\Url[] $urls */
    $urls = array_map(
      function ($langcode) use ($url_base, $languageManager) {
        $url = clone $url_base;
        return $url
          ->setOption('language', $languageManager->getLanguage($langcode));
      },
      array_combine($langcodes_all, $langcodes_all)
    );

    // Ensure link tags for all languages are found on each language variation
    // page of an entity.
    foreach ($urls as $langcode => $url) {
      $this->drupalGet($url);
      foreach ($urls as $langcode_alternate => $url_alternate) {
        $args = [':href' => $url_alternate->toString(), ':hreflang' => $langcode_alternate];
        $links = $this->xpath('head/link[@rel = "alternate" and @href = :href and @hreflang = :hreflang]', $args);
        $message = sprintf('The "%s" translation has the correct alternate hreflang link for "%s": %s.', $langcode, $langcode_alternate, $url->toString());
        $this->assertTrue(isset($links[0]), $message);
      }
    }
  }

  /**
   * Tests alternate link tag missing for entity types without canonical links.
   */
  public function testCanonicalAlternateTagsMissing() {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
    $entityTypeManager = $this->container->get('entity_type.manager');

    $definition = $entityTypeManager->getDefinition('entity_test_translatable_no_skip');
    // Ensure 'canonical' link template does not exist, in case it is added in
    // the future.
    $this->assertFalse($definition->hasLinkTemplate('canonical'), 'Canonical link template does not exist for entity_test_translatable_no_skip entity.');

    $entity = EntityTestTranslatableNoUISkip::create();
    $entity->save();
    $this->drupalGet($entity->toUrl('edit-form'));

    $this->assertSession()->statusCodeEquals(200);
    $result = $this->xpath('//link[@rel="alternate" and @hreflang]');
    $this->assertFalse($result, 'No alternate link tag found.');
  }

}
