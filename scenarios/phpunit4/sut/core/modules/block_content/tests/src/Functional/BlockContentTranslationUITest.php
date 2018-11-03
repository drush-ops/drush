<?php

namespace Drupal\Tests\block_content\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Tests\content_translation\Functional\ContentTranslationUITestBase;

/**
 * Tests the block content translation UI.
 *
 * @group block_content
 */
class BlockContentTranslationUITest extends ContentTranslationUITestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'language',
    'content_translation',
    'block',
    'field_ui',
    'block_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultCacheContexts = [
    'languages:language_interface',
    'session',
    'theme',
    'url.path',
    'url.query_args',
    'user.permissions',
    'user.roles:authenticated',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->entityTypeId = 'block_content';
    $this->bundle = 'basic';
    $this->testLanguageSelector = FALSE;
    parent::setUp();

    $this->drupalPlaceBlock('page_title_block');
  }

  /**
   * {@inheritdoc}
   */
  protected function setupBundle() {
    // Create the basic bundle since it is provided by standard.
    $bundle = BlockContentType::create([
      'id' => $this->bundle,
      'label' => $this->bundle,
      'revision' => FALSE,
    ]);
    $bundle->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatorPermissions() {
    return array_merge(parent::getTranslatorPermissions(), [
      'translate any entity',
      'access administration pages',
      'administer blocks',
      'administer block_content fields',
    ]);
  }

  /**
   * Creates a custom block.
   *
   * @param bool|string $title
   *   (optional) Title of block. When no value is given uses a random name.
   *   Defaults to FALSE.
   * @param bool|string $bundle
   *   (optional) Bundle name. When no value is given, defaults to
   *   $this->bundle. Defaults to FALSE.
   *
   * @return \Drupal\block_content\Entity\BlockContent
   *   Created custom block.
   */
  protected function createBlockContent($title = FALSE, $bundle = FALSE) {
    $title = $title ?: $this->randomMachineName();
    $bundle = $bundle ?: $this->bundle;
    $block_content = BlockContent::create([
      'info' => $title,
      'type' => $bundle,
      'langcode' => 'en',
    ]);
    $block_content->save();
    return $block_content;
  }

  /**
   * {@inheritdoc}
   */
  protected function getNewEntityValues($langcode) {
    return ['info' => mb_strtolower($this->randomMachineName())] + parent::getNewEntityValues($langcode);
  }

  /**
   * Returns an edit array containing the values to be posted.
   */
  protected function getEditValues($values, $langcode, $new = FALSE) {
    $edit = parent::getEditValues($values, $langcode, $new);
    foreach ($edit as $property => $value) {
      if ($property == 'info') {
        $edit['info[0][value]'] = $value;
        unset($edit[$property]);
      }
    }
    return $edit;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTestBasicTranslation() {
    parent::doTestBasicTranslation();

    // Ensure that a block translation can be created using the same description
    // as in the original language.
    $default_langcode = $this->langcodes[0];
    $values = $this->getNewEntityValues($default_langcode);
    $storage = \Drupal::entityManager()->getStorage($this->entityTypeId);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $storage->create(['type' => 'basic'] + $values);
    $entity->save();
    $entity->addTranslation('it', $values);

    try {
      $message = 'Blocks can have translations with the same "info" value.';
      $entity->save();
      $this->pass($message);
    }
    catch (\Exception $e) {
      $this->fail($message);
    }

    // Check that the translate operation link is shown.
    $this->drupalGet('admin/structure/block/block-content');
    $this->assertLinkByHref('block/' . $entity->id() . '/translations');
  }

  /**
   * Test that no metadata is stored for a disabled bundle.
   */
  public function testDisabledBundle() {
    // Create a bundle that does not have translation enabled.
    $disabled_bundle = $this->randomMachineName();
    $bundle = BlockContentType::create([
      'id' => $disabled_bundle,
      'label' => $disabled_bundle,
      'revision' => FALSE,
    ]);
    $bundle->save();

    // Create a block content for each bundle.
    $enabled_block_content = $this->createBlockContent();
    $disabled_block_content = $this->createBlockContent(FALSE, $bundle->id());

    // Make sure that only a single row was inserted into the block table.
    $rows = db_query('SELECT * FROM {block_content_field_data} WHERE id = :id', [':id' => $enabled_block_content->id()])->fetchAll();
    $this->assertEqual(1, count($rows));
  }

  /**
   * {@inheritdoc}
   */
  protected function doTestTranslationEdit() {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($this->entityTypeId);
    $storage->resetCache([$this->entityId]);
    $entity = $storage->load($this->entityId);
    $languages = $this->container->get('language_manager')->getLanguages();

    foreach ($this->langcodes as $langcode) {
      // We only want to test the title for non-english translations.
      if ($langcode != 'en') {
        $options = ['language' => $languages[$langcode]];
        $url = $entity->urlInfo('edit-form', $options);
        $this->drupalGet($url);

        $title = t('<em>Edit @type</em> @title [%language translation]', [
          '@type' => $entity->bundle(),
          '@title' => $entity->getTranslation($langcode)->label(),
          '%language' => $languages[$langcode]->getName(),
        ]);
        $this->assertRaw($title);
      }
    }
  }

}
