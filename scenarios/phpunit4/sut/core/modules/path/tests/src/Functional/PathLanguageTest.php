<?php

namespace Drupal\Tests\path\Functional;

/**
 * Confirm that paths work with translated nodes.
 *
 * @group path
 */
class PathLanguageTest extends PathTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['path', 'locale', 'locale_test', 'content_translation'];

  /**
   * An user with permissions to administer content types.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  protected function setUp() {
    parent::setUp();

    $permissions = [
      'access administration pages',
      'administer content translation',
      'administer content types',
      'administer languages',
      'administer url aliases',
      'create content translations',
      'create page content',
      'create url aliases',
      'edit any page content',
      'translate any entity',
    ];
    // Create and log in user.
    $this->webUser = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->webUser);

    // Enable French language.
    $edit = [];
    $edit['predefined_langcode'] = 'fr';

    $this->drupalPostForm('admin/config/regional/language/add', $edit, t('Add language'));

    // Enable URL language detection and selection.
    $edit = ['language_interface[enabled][language-url]' => 1];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));

    // Enable translation for page node.
    $edit = [
      'entity_types[node]' => 1,
      'settings[node][page][translatable]' => 1,
      'settings[node][page][fields][path]' => 1,
      'settings[node][page][fields][body]' => 1,
      'settings[node][page][settings][language][language_alterable]' => 1,
    ];
    $this->drupalPostForm('admin/config/regional/content-language', $edit, t('Save configuration'));
    \Drupal::entityManager()->clearCachedDefinitions();

    $definitions = \Drupal::entityManager()->getFieldDefinitions('node', 'page');
    $this->assertTrue($definitions['path']->isTranslatable(), 'Node path is translatable.');
    $this->assertTrue($definitions['body']->isTranslatable(), 'Node body is translatable.');
  }

  /**
   * Test alias functionality through the admin interfaces.
   */
  public function testAliasTranslation() {
    $node_storage = $this->container->get('entity.manager')->getStorage('node');
    $english_node = $this->drupalCreateNode(['type' => 'page', 'langcode' => 'en']);
    $english_alias = $this->randomMachineName();

    // Edit the node to set language and path.
    $edit = [];
    $edit['path[0][alias]'] = '/' . $english_alias;
    $this->drupalPostForm('node/' . $english_node->id() . '/edit', $edit, t('Save'));

    // Confirm that the alias works.
    $this->drupalGet($english_alias);
    $this->assertText($english_node->body->value, 'Alias works.');

    // Translate the node into French.
    $this->drupalGet('node/' . $english_node->id() . '/translations');
    $this->clickLink(t('Add'));

    $edit = [];
    $edit['title[0][value]'] = $this->randomMachineName();
    $edit['body[0][value]'] = $this->randomMachineName();
    $french_alias = $this->randomMachineName();
    $edit['path[0][alias]'] = '/' . $french_alias;
    $this->drupalPostForm(NULL, $edit, t('Save (this translation)'));

    // Clear the path lookup cache.
    $this->container->get('path.alias_manager')->cacheClear();

    // Languages are cached on many levels, and we need to clear those caches.
    $this->container->get('language_manager')->reset();
    $this->rebuildContainer();
    $languages = $this->container->get('language_manager')->getLanguages();

    // Ensure the node was created.
    $node_storage->resetCache([$english_node->id()]);
    $english_node = $node_storage->load($english_node->id());
    $english_node_french_translation = $english_node->getTranslation('fr');
    $this->assertTrue($english_node->hasTranslation('fr'), 'Node found in database.');

    // Confirm that the alias works.
    $this->drupalGet('fr' . $edit['path[0][alias]']);
    $this->assertText($english_node_french_translation->body->value, 'Alias for French translation works.');

    // Confirm that the alias is returned for the URL. Languages are cached on
    // many levels, and we need to clear those caches.
    $this->container->get('language_manager')->reset();
    $languages = $this->container->get('language_manager')->getLanguages();
    $url = $english_node_french_translation->url('canonical', ['language' => $languages['fr']]);

    $this->assertTrue(strpos($url, $edit['path[0][alias]']), 'URL contains the path alias.');

    // Confirm that the alias works even when changing language negotiation
    // options. Enable User language detection and selection over URL one.
    $edit = [
      'language_interface[enabled][language-user]' => 1,
      'language_interface[weight][language-user]' => -9,
      'language_interface[enabled][language-url]' => 1,
      'language_interface[weight][language-url]' => -8,
    ];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));

    // Change user language preference.
    $edit = ['preferred_langcode' => 'fr'];
    $this->drupalPostForm("user/" . $this->webUser->id() . "/edit", $edit, t('Save'));

    // Check that the English alias works. In this situation French is the
    // current UI and content language, while URL language is English (since we
    // do not have a path prefix we fall back to the site's default language).
    // We need to ensure that the user language preference is not taken into
    // account while determining the path alias language, because if this
    // happens we have no way to check that the path alias is valid: there is no
    // path alias for French matching the english alias. So the alias manager
    // needs to use the URL language to check whether the alias is valid.
    $this->drupalGet($english_alias);
    $this->assertText($english_node_french_translation->body->value, 'English alias, but French preferred by the user: French translation.');

    // Check that the French alias works.
    $this->drupalGet("fr/$french_alias");
    $this->assertText($english_node_french_translation->body->value, 'Alias for French translation works.');

    // Disable URL language negotiation.
    $edit = ['language_interface[enabled][language-url]' => FALSE];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));

    // Check that the English alias still works.
    $this->drupalGet($english_alias);
    $this->assertText($english_node_french_translation->body->value, 'English alias, but French preferred by the user: French translation.');

    // Check that the French alias is not available. We check the unprefixed
    // alias because we disabled URL language negotiation above. In this
    // situation only aliases in the default language and language neutral ones
    // should keep working.
    $this->drupalGet($french_alias);
    $this->assertResponse(404, 'Alias for French translation is unavailable when URL language negotiation is disabled.');

    // The alias manager has an internal path lookup cache. Check to see that
    // it has the appropriate contents at this point.
    $this->container->get('path.alias_manager')->cacheClear();
    $french_node_path = $this->container->get('path.alias_manager')->getPathByAlias('/' . $french_alias, 'fr');
    $this->assertEqual($french_node_path, '/node/' . $english_node_french_translation->id(), 'Normal path works.');
    // Second call should return the same path.
    $french_node_path = $this->container->get('path.alias_manager')->getPathByAlias('/' . $french_alias, 'fr');
    $this->assertEqual($french_node_path, '/node/' . $english_node_french_translation->id(), 'Normal path is the same.');

    // Confirm that the alias works.
    $french_node_alias = $this->container->get('path.alias_manager')->getAliasByPath('/node/' . $english_node_french_translation->id(), 'fr');
    $this->assertEqual($french_node_alias, '/' . $french_alias, 'Alias works.');
    // Second call should return the same alias.
    $french_node_alias = $this->container->get('path.alias_manager')->getAliasByPath('/node/' . $english_node_french_translation->id(), 'fr');
    $this->assertEqual($french_node_alias, '/' . $french_alias, 'Alias is the same.');

    // Confirm that the alias is removed if the translation is deleted.
    $english_node->removeTranslation('fr');
    $english_node->save();
    $this->assertFalse($this->container->get('path.alias_storage')->aliasExists('/' . $french_alias, 'fr'), 'Alias for French translation is removed when translation is deleted.');

    // Check that the English alias still works.
    $this->drupalGet($english_alias);
    $this->assertTrue($this->container->get('path.alias_storage')->aliasExists('/' . $english_alias, 'en'), 'English alias is not deleted when French translation is removed.');
    $this->assertText($english_node->body->value, 'English alias still works');
  }

}
