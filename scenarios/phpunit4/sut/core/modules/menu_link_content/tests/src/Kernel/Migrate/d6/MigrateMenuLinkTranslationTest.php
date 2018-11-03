<?php

namespace Drupal\Tests\menu_link_content\Kernel\Migrate\d6;

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;

/**
 * Menu link migration.
 *
 * @group migrate_drupal_6
 */
class MigrateMenuLinkTranslationTest extends MigrateDrupal6TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'menu_ui',
    'menu_link_content',
    'language',
    'content_translation',
    // Required for translation migrations.
    'migrate_drupal_multilingual',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->migrateContent();
    $this->installSchema('system', ['router']);
    $this->installEntitySchema('menu_link_content');
    $this->executeMigrations([
      'language',
      'd6_menu',
      'd6_menu_links',
      'd6_menu_links_translation',
    ]);
  }

  /**
   * Tests migration of menu links.
   */
  public function testMenuLinks() {
    /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $menu_link */
    $menu_link = MenuLinkContent::load(139)->getTranslation('fr');
    $this->assertInstanceOf(MenuLinkContent::class, $menu_link);
    $this->assertSame('fr - Test 2', $menu_link->getTitle());
    $this->assertSame('fr - Test menu link 2', $menu_link->getDescription());
    $this->assertSame('secondary-links', $menu_link->getMenuName());
    $this->assertTrue($menu_link->isEnabled());
    $this->assertTrue($menu_link->isExpanded());
    $this->assertSame(['query' => 'foo=bar', 'attributes' => ['title' => 'Test menu link 2']], $menu_link->link->options);
    $this->assertSame('internal:/admin', $menu_link->link->uri);
    $this->assertSame(-49, $menu_link->getWeight());

    $menu_link = MenuLinkContent::load(139)->getTranslation('zu');
    $this->assertInstanceOf(MenuLinkContent::class, $menu_link);
    $this->assertSame('Test 2', $menu_link->getTitle());
    $this->assertSame('zu - Test menu link 2', $menu_link->getDescription());
    $this->assertSame('secondary-links', $menu_link->getMenuName());
    $this->assertTrue($menu_link->isEnabled());
    $this->assertTrue($menu_link->isExpanded());
    $this->assertSame(['query' => 'foo=bar', 'attributes' => ['title' => 'Test menu link 2']], $menu_link->link->options);
    $this->assertSame('internal:/admin', $menu_link->link->uri);
    $this->assertSame(-49, $menu_link->getWeight());

    $menu_link = MenuLinkContent::load(140)->getTranslation('fr');
    $this->assertInstanceOf(MenuLinkContent::class, $menu_link);
    $this->assertSame('fr - Drupal.org', $menu_link->getTitle());
    $this->assertSame('', $menu_link->getDescription());
    $this->assertSame('secondary-links', $menu_link->getMenuName());
    $this->assertTrue($menu_link->isEnabled());
    $this->assertFalse($menu_link->isExpanded());
    $this->assertSame(['attributes' => ['title' => '']], $menu_link->link->options);
    $this->assertSame('https://www.drupal.org', $menu_link->link->uri);
    $this->assertSame(-50, $menu_link->getWeight());

    $menu_link = MenuLinkContent::load(463);
    $this->assertInstanceOf(MenuLinkContent::class, $menu_link);
    $this->assertSame('fr - Test 1', $menu_link->getTitle());
    $this->assertSame('fr - Test menu link 1', $menu_link->getDescription());
    $this->assertSame('secondary-links', $menu_link->getMenuName());
    $this->assertTrue($menu_link->isEnabled());
    $this->assertFalse($menu_link->isExpanded());
    $attributes = [
      'attributes' => [
        'title' => 'fr - Test menu link 1',
      ],
      'langcode' => 'fr',
      'alter' => TRUE,
    ];
    $this->assertSame($attributes, $menu_link->link->options);
    $this->assertSame('internal:/user/login', $menu_link->link->uri);
    $this->assertSame(-49, $menu_link->getWeight());
  }

}
