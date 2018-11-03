<?php

namespace Drupal\Tests\taxonomy\Functional;

use Drupal\Component\Utility\Unicode;

/**
 * Tests the taxonomy vocabulary permissions.
 *
 * @group taxonomy
 */
class VocabularyPermissionsTest extends TaxonomyTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['help'];

  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('page_title_block');
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('help_block');
  }

  /**
   * Create, edit and delete a vocabulary via the user interface.
   */
  public function testVocabularyPermissionsVocabulary() {
    // VocabularyTest.php already tests for user with "administer taxonomy"
    // permission.

    // Test as user without proper permissions.
    $authenticated_user = $this->drupalCreateUser([]);
    $this->drupalLogin($authenticated_user);

    $assert_session = $this->assertSession();

    // Visit the main taxonomy administration page.
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(403);

    // Test as user with "access taxonomy overview" permissions.
    $proper_user = $this->drupalCreateUser(['access taxonomy overview']);
    $this->drupalLogin($proper_user);

    // Visit the main taxonomy administration page.
    $this->drupalGet('admin/structure/taxonomy');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Vocabulary name');
    $assert_session->linkNotExists('Add vocabulary');
  }

  /**
   * Test the vocabulary overview permission.
   */
  public function testTaxonomyVocabularyOverviewPermissions() {
    // Create two vocabularies, one with two terms, the other without any term.
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary1 , $vocabulary2 */
    $vocabulary1 = $this->createVocabulary();
    $vocabulary2 = $this->createVocabulary();
    $vocabulary1_id = $vocabulary1->id();
    $vocabulary2_id = $vocabulary2->id();
    $this->createTerm($vocabulary1);
    $this->createTerm($vocabulary1);

    // Assert expected help texts on first vocabulary.
    $edit_help_text = t('You can reorganize the terms in @capital_name using their drag-and-drop handles, and group terms under a parent term by sliding them under and to the right of the parent.', ['@capital_name' => Unicode::ucfirst($vocabulary1->label())]);
    $no_edit_help_text = t('@capital_name contains the following terms.', ['@capital_name' => Unicode::ucfirst($vocabulary1->label())]);

    $assert_session = $this->assertSession();

    // Logged in as admin user with 'administer taxonomy' permission.
    $admin_user = $this->drupalCreateUser(['administer taxonomy']);
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary1_id . '/overview');
    $assert_session->statusCodeEquals(200);
    $assert_session->linkExists('Edit');
    $assert_session->linkExists('Delete');
    $assert_session->linkExists('Add term');
    $assert_session->buttonExists('Save');
    $assert_session->pageTextContains('Weight');
    $assert_session->fieldExists('Weight');
    $assert_session->pageTextContains($edit_help_text);

    // Visit vocabulary overview without terms. 'Add term' should be shown.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary2_id . '/overview');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('No terms available');
    $assert_session->linkExists('Add term');

    // Login as a user without any of the required permissions.
    $no_permission_user = $this->drupalCreateUser();
    $this->drupalLogin($no_permission_user);
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary1_id . '/overview');
    $assert_session->statusCodeEquals(403);
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary2_id . '/overview');
    $assert_session->statusCodeEquals(403);

    // Log in as a user with only the overview permission, neither edit nor
    // delete operations must be available and no Save button.
    $overview_only_user = $this->drupalCreateUser(['access taxonomy overview']);
    $this->drupalLogin($overview_only_user);
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary1_id . '/overview');
    $assert_session->statusCodeEquals(200);
    $assert_session->linkNotExists('Edit');
    $assert_session->linkNotExists('Delete');
    $assert_session->buttonNotExists('Save');
    $assert_session->pageTextContains('Weight');
    $assert_session->fieldNotExists('Weight');
    $assert_session->linkNotExists('Add term');
    $assert_session->pageTextContains($no_edit_help_text);

    // Visit vocabulary overview without terms. 'Add term' should not be shown.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary2_id . '/overview');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('No terms available');
    $assert_session->linkNotExists('Add term');

    // Login as a user with permission to edit terms, only edit link should be
    // visible.
    $edit_user = $this->createUser([
      'access taxonomy overview',
      'edit terms in ' . $vocabulary1_id,
      'edit terms in ' . $vocabulary2_id,
    ]);
    $this->drupalLogin($edit_user);
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary1_id . '/overview');
    $assert_session->statusCodeEquals(200);
    $assert_session->linkExists('Edit');
    $assert_session->linkNotExists('Delete');
    $assert_session->buttonExists('Save');
    $assert_session->pageTextContains('Weight');
    $assert_session->fieldExists('Weight');
    $assert_session->linkNotExists('Add term');
    $assert_session->pageTextContains($edit_help_text);

    // Visit vocabulary overview without terms. 'Add term' should not be shown.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary2_id . '/overview');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('No terms available');
    $assert_session->linkNotExists('Add term');

    // Login as a user with permission only to delete terms.
    $edit_delete_user = $this->createUser([
      'access taxonomy overview',
      'delete terms in ' . $vocabulary1_id,
      'delete terms in ' . $vocabulary2_id,
    ]);
    $this->drupalLogin($edit_delete_user);
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary1_id . '/overview');
    $assert_session->statusCodeEquals(200);
    $assert_session->linkNotExists('Edit');
    $assert_session->linkExists('Delete');
    $assert_session->linkNotExists('Add term');
    $assert_session->buttonNotExists('Save');
    $assert_session->pageTextContains('Weight');
    $assert_session->fieldNotExists('Weight');
    $assert_session->pageTextContains($no_edit_help_text);

    // Visit vocabulary overview without terms. 'Add term' should not be shown.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary2_id . '/overview');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('No terms available');
    $assert_session->linkNotExists('Add term');

    // Login as a user with permission to edit and delete terms.
    $edit_delete_user = $this->createUser([
      'access taxonomy overview',
      'edit terms in ' . $vocabulary1_id,
      'delete terms in ' . $vocabulary1_id,
      'edit terms in ' . $vocabulary2_id,
      'delete terms in ' . $vocabulary2_id,
    ]);
    $this->drupalLogin($edit_delete_user);
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary1_id . '/overview');
    $assert_session->statusCodeEquals(200);
    $assert_session->linkExists('Edit');
    $assert_session->linkExists('Delete');
    $assert_session->linkNotExists('Add term');
    $assert_session->buttonExists('Save');
    $assert_session->pageTextContains('Weight');
    $assert_session->fieldExists('Weight');
    $assert_session->pageTextContains($edit_help_text);

    // Visit vocabulary overview without terms. 'Add term' should not be shown.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary2_id . '/overview');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('No terms available');
    $assert_session->linkNotExists('Add term');

    // Login as a user with permission to create new terms, only add new term
    // link should be visible.
    $edit_user = $this->createUser([
      'access taxonomy overview',
      'create terms in ' . $vocabulary1_id,
      'create terms in ' . $vocabulary2_id,
    ]);
    $this->drupalLogin($edit_user);
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary1_id . '/overview');
    $assert_session->statusCodeEquals(200);
    $assert_session->linkNotExists('Edit');
    $assert_session->linkNotExists('Delete');
    $assert_session->linkExists('Add term');
    $assert_session->buttonNotExists('Save');
    $assert_session->pageTextContains('Weight');
    $assert_session->fieldNotExists('Weight');
    $assert_session->pageTextContains($no_edit_help_text);

    // Visit vocabulary overview without terms. 'Add term' should not be shown.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary2_id . '/overview');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('No terms available');
    $assert_session->linkExists('Add term');
  }

  /**
   * Create, edit and delete a taxonomy term via the user interface.
   */
  public function testVocabularyPermissionsTaxonomyTerm() {
    // Vocabulary used for creating, removing and editing terms.
    $vocabulary = $this->createVocabulary();

    // Test as admin user.
    $user = $this->drupalCreateUser(['administer taxonomy']);
    $this->drupalLogin($user);

    // Visit the main taxonomy administration page.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
    $this->assertResponse(200);
    $this->assertField('edit-name-0-value', 'Add taxonomy term form opened successfully.');

    // Submit the term.
    $edit = [];
    $edit['name[0][value]'] = $this->randomMachineName();

    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertText(t('Created new term @name.', ['@name' => $edit['name[0][value]']]), 'Term created successfully.');

    // Verify that the creation message contains a link to a term.
    $view_link = $this->xpath('//div[@class="messages"]//a[contains(@href, :href)]', [':href' => 'term/']);
    $this->assert(isset($view_link), 'The message area contains a link to a term');

    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $edit['name[0][value]']]);
    $term = reset($terms);

    // Edit the term.
    $this->drupalGet('taxonomy/term/' . $term->id() . '/edit');
    $this->assertResponse(200);
    $this->assertText($edit['name[0][value]'], 'Edit taxonomy term form opened successfully.');

    $edit['name[0][value]'] = $this->randomMachineName();
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertText(t('Updated term @name.', ['@name' => $edit['name[0][value]']]), 'Term updated successfully.');

    // Delete the vocabulary.
    $this->drupalGet('taxonomy/term/' . $term->id() . '/delete');
    $this->assertRaw(t('Are you sure you want to delete the @entity-type %label?', ['@entity-type' => 'taxonomy term', '%label' => $edit['name[0][value]']]), 'Delete taxonomy term form opened successfully.');

    // Confirm deletion.
    $this->drupalPostForm(NULL, NULL, t('Delete'));
    $this->assertRaw(t('Deleted term %name.', ['%name' => $edit['name[0][value]']]), 'Term deleted.');

    // Test as user with "create" permissions.
    $user = $this->drupalCreateUser(["create terms in {$vocabulary->id()}"]);
    $this->drupalLogin($user);

    $assert_session = $this->assertSession();

    // Create a new term.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
    $assert_session->statusCodeEquals(200);
    $assert_session->fieldExists('name[0][value]');

    // Submit the term.
    $edit = [];
    $edit['name[0][value]'] = $this->randomMachineName();

    $this->drupalPostForm(NULL, $edit, t('Save'));
    $assert_session->pageTextContains(t('Created new term @name.', ['@name' => $edit['name[0][value]']]));

    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $edit['name[0][value]']]);
    $term = reset($terms);

    // Ensure that edit and delete access is denied.
    $this->drupalGet('taxonomy/term/' . $term->id() . '/edit');
    $assert_session->statusCodeEquals(403);
    $this->drupalGet('taxonomy/term/' . $term->id() . '/delete');
    $assert_session->statusCodeEquals(403);

    // Test as user with "edit" permissions.
    $user = $this->drupalCreateUser(["edit terms in {$vocabulary->id()}"]);
    $this->drupalLogin($user);

    // Visit the main taxonomy administration page.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
    $this->assertResponse(403, 'Add taxonomy term form open failed.');

    // Create a test term.
    $term = $this->createTerm($vocabulary);

    // Edit the term.
    $this->drupalGet('taxonomy/term/' . $term->id() . '/edit');
    $this->assertResponse(200);
    $this->assertText($term->getName(), 'Edit taxonomy term form opened successfully.');

    $edit['name[0][value]'] = $this->randomMachineName();
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertText(t('Updated term @name.', ['@name' => $edit['name[0][value]']]), 'Term updated successfully.');

    // Verify that the update message contains a link to a term.
    $view_link = $this->xpath('//div[@class="messages"]//a[contains(@href, :href)]', [':href' => 'term/']);
    $this->assert(isset($view_link), 'The message area contains a link to a term');

    // Delete the vocabulary.
    $this->drupalGet('taxonomy/term/' . $term->id() . '/delete');
    $this->assertResponse(403, 'Delete taxonomy term form open failed.');

    // Test as user with "delete" permissions.
    $user = $this->drupalCreateUser(["delete terms in {$vocabulary->id()}"]);
    $this->drupalLogin($user);

    // Visit the main taxonomy administration page.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
    $this->assertResponse(403, 'Add taxonomy term form open failed.');

    // Create a test term.
    $term = $this->createTerm($vocabulary);

    // Edit the term.
    $this->drupalGet('taxonomy/term/' . $term->id() . '/edit');
    $this->assertResponse(403, 'Edit taxonomy term form open failed.');

    // Delete the vocabulary.
    $this->drupalGet('taxonomy/term/' . $term->id() . '/delete');
    $this->assertRaw(t('Are you sure you want to delete the @entity-type %label?', ['@entity-type' => 'taxonomy term', '%label' => $term->getName()]), 'Delete taxonomy term form opened successfully.');

    // Confirm deletion.
    $this->drupalPostForm(NULL, NULL, t('Delete'));
    $this->assertRaw(t('Deleted term %name.', ['%name' => $term->getName()]), 'Term deleted.');

    // Test as user without proper permissions.
    $user = $this->drupalCreateUser();
    $this->drupalLogin($user);

    // Visit the main taxonomy administration page.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
    $this->assertResponse(403, 'Add taxonomy term form open failed.');

    // Create a test term.
    $term = $this->createTerm($vocabulary);

    // Edit the term.
    $this->drupalGet('taxonomy/term/' . $term->id() . '/edit');
    $this->assertResponse(403, 'Edit taxonomy term form open failed.');

    // Delete the vocabulary.
    $this->drupalGet('taxonomy/term/' . $term->id() . '/delete');
    $this->assertResponse(403, 'Delete taxonomy term form open failed.');
  }

}
