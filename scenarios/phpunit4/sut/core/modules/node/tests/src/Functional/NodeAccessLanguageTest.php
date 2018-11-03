<?php

namespace Drupal\Tests\node\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Tests node_access and db_select() with node_access tag functionality with
 * multiple languages with a test node access module that is not language-aware.
 *
 * @group node
 */
class NodeAccessLanguageTest extends NodeTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['language', 'node_access_test'];

  protected function setUp() {
    parent::setUp();

    node_access_test_add_field(NodeType::load('page'));

    // After enabling a node access module, the access table has to be rebuild.
    node_access_rebuild();

    // Enable the private node feature of the node_access_test module.
    \Drupal::state()->set('node_access_test.private', TRUE);

    // Add Hungarian, Catalan and Croatian.
    ConfigurableLanguage::createFromLangcode('hu')->save();
    ConfigurableLanguage::createFromLangcode('ca')->save();
    ConfigurableLanguage::createFromLangcode('hr')->save();
  }

  /**
   * Tests node access with multiple node languages and no private nodes.
   */
  public function testNodeAccess() {
    $web_user = $this->drupalCreateUser(['access content']);

    $expected_node_access = ['view' => TRUE, 'update' => FALSE, 'delete' => FALSE];
    $expected_node_access_no_access = ['view' => FALSE, 'update' => FALSE, 'delete' => FALSE];

    // Creating a public node with langcode Hungarian, will be saved as the
    // fallback in node access table.
    $node_public_hu = $this->drupalCreateNode(['body' => [[]], 'langcode' => 'hu', 'private' => FALSE]);
    $this->assertTrue($node_public_hu->language()->getId() == 'hu', 'Node created as Hungarian.');

    // Tests the default access is provided for the public Hungarian node.
    $this->assertNodeAccess($expected_node_access, $node_public_hu, $web_user);

    // Tests that Hungarian provided specifically results in the same.
    $this->assertNodeAccess($expected_node_access, $node_public_hu->getTranslation('hu'), $web_user);

    // Creating a public node with no special langcode, like when no language
    // module enabled.
    $node_public_no_language = $this->drupalCreateNode([
      'private' => FALSE,
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $this->assertTrue($node_public_no_language->language()->getId() == LanguageInterface::LANGCODE_NOT_SPECIFIED, 'Node created with not specified language.');

    // Tests that access is granted if requested with no language.
    $this->assertNodeAccess($expected_node_access, $node_public_no_language, $web_user);

    // Reset the node access cache and turn on our test node access code.
    \Drupal::entityManager()->getAccessControlHandler('node')->resetCache();
    \Drupal::state()->set('node_access_test_secret_catalan', 1);
    $node_public_ca = $this->drupalCreateNode(['body' => [[]], 'langcode' => 'ca', 'private' => FALSE]);
    $this->assertTrue($node_public_ca->language()->getId() == 'ca', 'Node created as Catalan.');

    // Tests that access is granted if requested with no language.
    $this->assertNodeAccess($expected_node_access, $node_public_no_language, $web_user);
    $this->assertNodeAccess($expected_node_access_no_access, $node_public_ca, $web_user);

    // Tests that Hungarian node is still accessible.
    $this->assertNodeAccess($expected_node_access, $node_public_hu, $web_user);
    $this->assertNodeAccess($expected_node_access, $node_public_hu->getTranslation('hu'), $web_user);

    // Tests that Catalan is still not accessible.
    $this->assertNodeAccess($expected_node_access_no_access, $node_public_ca->getTranslation('ca'), $web_user);

    // Make Catalan accessible.
    \Drupal::state()->set('node_access_test_secret_catalan', 0);

    // Tests that Catalan is accessible on a node with a Catalan version as the
    // static cache has not been reset.
    $this->assertNodeAccess($expected_node_access_no_access, $node_public_ca, $web_user);
    $this->assertNodeAccess($expected_node_access_no_access, $node_public_ca->getTranslation('ca'), $web_user);

    \Drupal::entityManager()->getAccessControlHandler('node')->resetCache();

    // Tests that access is granted if requested with no language.
    $this->assertNodeAccess($expected_node_access, $node_public_no_language, $web_user);
    $this->assertNodeAccess($expected_node_access, $node_public_ca, $web_user);

    // Tests that Hungarian node is still accessible.
    $this->assertNodeAccess($expected_node_access, $node_public_hu, $web_user);
    $this->assertNodeAccess($expected_node_access, $node_public_hu->getTranslation('hu'), $web_user);

    // Tests that Catalan is accessible on a node with a Catalan version.
    $this->assertNodeAccess($expected_node_access, $node_public_ca->getTranslation('ca'), $web_user);
  }

  /**
   * Tests node access with multiple node languages and private nodes.
   */
  public function testNodeAccessPrivate() {
    $web_user = $this->drupalCreateUser(['access content']);
    $expected_node_access = ['view' => TRUE, 'update' => FALSE, 'delete' => FALSE];
    $expected_node_access_no_access = ['view' => FALSE, 'update' => FALSE, 'delete' => FALSE];

    // Creating a private node with langcode Hungarian, will be saved as the
    // fallback in node access table.
    $node_private_hu = $this->drupalCreateNode(['body' => [[]], 'langcode' => 'hu', 'private' => TRUE]);
    $this->assertTrue($node_private_hu->language()->getId() == 'hu', 'Node created as Hungarian.');

    // Tests the default access is not provided for the private Hungarian node.
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_hu, $web_user);

    // Tests that Hungarian provided specifically results in the same.
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_hu->getTranslation('hu'), $web_user);

    // Creating a private node with no special langcode, like when no language
    // module enabled.
    $node_private_no_language = $this->drupalCreateNode([
      'private' => TRUE,
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $this->assertTrue($node_private_no_language->language()->getId() == LanguageInterface::LANGCODE_NOT_SPECIFIED, 'Node created with not specified language.');

    // Tests that access is not granted if requested with no language.
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_no_language, $web_user);

    // Reset the node access cache and turn on our test node access code.
    \Drupal::entityManager()->getAccessControlHandler('node')->resetCache();
    \Drupal::state()->set('node_access_test_secret_catalan', 1);

    // Tests that access is not granted if requested with no language.
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_no_language, $web_user);

    // Creating a private node with langcode Catalan to test that the
    // node_access_test_secret_catalan flag works.
    $private_ca_user = $this->drupalCreateUser(['access content', 'node test view']);
    $node_private_ca = $this->drupalCreateNode(['body' => [[]], 'langcode' => 'ca', 'private' => TRUE]);
    $this->assertTrue($node_private_ca->language()->getId() == 'ca', 'Node created as Catalan.');

    // Tests that Catalan is still not accessible to either user.
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_ca, $web_user);
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_ca->getTranslation('ca'), $web_user);
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_ca, $private_ca_user);
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_ca->getTranslation('ca'), $private_ca_user);

    \Drupal::entityManager()->getAccessControlHandler('node')->resetCache();
    \Drupal::state()->set('node_access_test_secret_catalan', 0);

    // Tests that Catalan is still not accessible for a user with no access to
    // private nodes.
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_ca, $web_user);
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_ca->getTranslation('ca'), $web_user);

    // Tests that Catalan is accessible by a user with the permission to see
    // private nodes.
    $this->assertNodeAccess($expected_node_access, $node_private_ca, $private_ca_user);
    $this->assertNodeAccess($expected_node_access, $node_private_ca->getTranslation('ca'), $private_ca_user);
  }

  /**
   * Tests db_select() with a 'node_access' tag and langcode metadata.
   */
  public function testNodeAccessQueryTag() {
    // Create a normal authenticated user.
    $web_user = $this->drupalCreateUser(['access content']);

    // Load the user 1 user for later use as an admin user with permission to
    // see everything.
    $admin_user = User::load(1);

    // Creating a private node with langcode Hungarian, will be saved as
    // the fallback in node access table.
    $node_private = $this->drupalCreateNode(['body' => [[]], 'langcode' => 'hu', 'private' => TRUE]);
    $this->assertTrue($node_private->language()->getId() == 'hu', 'Node created as Hungarian.');

    // Creating a public node with langcode Hungarian, will be saved as
    // the fallback in node access table.
    $node_public = $this->drupalCreateNode(['body' => [[]], 'langcode' => 'hu', 'private' => FALSE]);
    $this->assertTrue($node_public->language()->getId() == 'hu', 'Node created as Hungarian.');

    // Creating a public node with no special langcode, like when no language
    // module enabled.
    $node_no_language = $this->drupalCreateNode([
      'private' => FALSE,
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $this->assertTrue($node_no_language->language()->getId() == LanguageInterface::LANGCODE_NOT_SPECIFIED, 'Node created with not specified language.');

    // Query the nodes table as the web user with the node access tag and no
    // specific langcode.
    $select = db_select('node', 'n')
      ->fields('n', ['nid'])
      ->addMetaData('account', $web_user)
      ->addTag('node_access');
    $nids = $select->execute()->fetchAllAssoc('nid');

    // The public node and no language node should be returned. Because no
    // langcode is given it will use the fallback node.
    $this->assertEqual(count($nids), 2, 'db_select() returns 2 node');
    $this->assertTrue(array_key_exists($node_public->id(), $nids), 'Returned node ID is public node.');
    $this->assertTrue(array_key_exists($node_no_language->id(), $nids), 'Returned node ID is no language node.');

    // Query the nodes table as the web user with the node access tag and
    // langcode de.
    $select = db_select('node', 'n')
      ->fields('n', ['nid'])
      ->addMetaData('account', $web_user)
      ->addMetaData('langcode', 'de')
      ->addTag('node_access');
    $nids = $select->execute()->fetchAllAssoc('nid');

    // Because no nodes are created in German, no nodes are returned.
    $this->assertTrue(empty($nids), 'db_select() returns an empty result.');

    // Query the nodes table as admin user (full access) with the node access
    // tag and no specific langcode.
    $select = db_select('node', 'n')
      ->fields('n', ['nid'])
      ->addMetaData('account', $admin_user)
      ->addTag('node_access');
    $nids = $select->execute()->fetchAllAssoc('nid');

    // All nodes are returned.
    $this->assertEqual(count($nids), 3, 'db_select() returns all three nodes.');

    // Query the nodes table as admin user (full access) with the node access
    // tag and langcode de.
    $select = db_select('node', 'n')
      ->fields('n', ['nid'])
      ->addMetaData('account', $admin_user)
      ->addMetaData('langcode', 'de')
      ->addTag('node_access');
    $nids = $select->execute()->fetchAllAssoc('nid');

    // All nodes are returned because node access tag is not invoked when the
    // user is user 1.
    $this->assertEqual(count($nids), 3, 'db_select() returns all three nodes.');
  }

}
