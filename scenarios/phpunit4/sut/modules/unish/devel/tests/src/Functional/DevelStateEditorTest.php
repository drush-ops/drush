<?php

namespace Drupal\Tests\devel\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests devel state editor.
 *
 * @group devel
 */
class DevelStateEditorTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['devel', 'block'];

  /**
   * The state store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The user for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $develUser;

  /**
   * The user for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->state = $this->container->get('state');

    $this->drupalPlaceBlock('page_title_block');

    $this->develUser = $this->drupalCreateUser(['access devel information']);
    $this->adminUser = $this->drupalCreateUser(['access devel information', 'administer site configuration']);
  }

  /**
   * Tests state editor menu link.
   */
  public function testStateEditMenuLink() {
    $this->drupalPlaceBlock('system_menu_block:devel');
    $this->drupalLogin($this->develUser);
    // Ensures that the state editor link is present on the devel menu and that
    // it points to the correct page.
    $this->drupalGet('');
    $this->clickLink('State editor');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('/devel/state');
    $this->assertSession()->pageTextContains('State editor');
  }

  /**
   * Tests state listing.
   */
  public function testStateListing() {
    $table_selector = 'table.devel-state-list';

    // Ensure that state listing page is accessible only by users with the
    // adequate permissions.
    $this->drupalGet('devel/state');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->develUser);
    $this->drupalGet('devel/state');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('State editor');

    // Ensure that the state variables table is visible.
    $table = $this->assertSession()->elementExists('css', $table_selector);

    // Ensure that all state variables are listed in the table.
    $states = \Drupal::keyValue('state')->getAll();
    $rows = $table->findAll('css', 'tbody tr');
    $this->assertEquals(count($rows), count($states), 'All states are listed in the table.');

    // Ensure that the added state variables are listed in the table.
    $this->state->set('devel.simple', 'Hello!');
    $this->drupalGet('devel/state');
    $table = $this->assertSession()->elementExists('css', $table_selector);
    $this->assertSession()->elementExists('css', sprintf('tbody td:contains("%s")', 'devel.simple'), $table);

    // Ensure that the operations column and the actions buttons are not
    // available for user without 'administer site configuration' permission.
    $headers = $table->findAll('css', 'thead th');
    $this->assertEquals(count($headers), 2, 'Correct number of table header cells found.');
    $this->assertElementsTextEquals($headers, ['Name', 'Value']);
    $this->assertSession()->elementNotExists('css', 'ul.dropbutton li a', $table);

    // Ensure that the operations column and the actions buttons are
    // available for user with 'administer site configuration' permission.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('devel/state');

    $table = $this->assertSession()->elementExists('css', $table_selector);
    $headers = $table->findAll('css', 'thead th');
    $this->assertEquals(count($headers), 3, 'Correct number of table header cells found.');
    $this->assertElementsTextEquals($headers, ['Name', 'Value', 'Operations']);
    $this->assertSession()->elementExists('css', 'ul.dropbutton li a', $table);

    // Test that the edit button works properly.
    $this->clickLink('Edit');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests state edit.
   */
  public function testStateEdit() {
    // Create some state variables for the test.
    $this->state->set('devel.simple', 0);
    $this->state->set('devel.array', ['devel' => 'value']);
    $this->state->set('devel.object', $this->randomObject());

    // Ensure that state edit form is accessible only by users with the
    // adequate permissions.
    $this->drupalLogin($this->develUser);
    $this->drupalGet('devel/state/edit/devel.simple');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->adminUser);

    // Ensure that accessing an un-existent state variable cause a warning
    // message.
    $this->drupalGet('devel/state/edit/devel.unknown');
    $this->assertSession()->pageTextContains(strtr('State @name does not exist in the system.', ['@name' => 'devel.unknown']));

    // Ensure that state variables that contain simple type can be edited and
    // saved.
    $this->drupalGet('devel/state/edit/devel.simple');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(strtr('Edit state variable: @name', ['@name' => 'devel.simple']));
    $input = $this->assertSession()->fieldExists('edit-new-value');
    $this->assertFalse($input->hasAttribute('disabled'));
    $button = $this->assertSession()->buttonExists('edit-submit');
    $this->assertFalse($button->hasAttribute('disabled'));

    $edit = ['new_value' => 1];
    $this->drupalPostForm('devel/state/edit/devel.simple', $edit, 'Save');
    $this->assertSession()->pageTextContains(strtr('Variable @name was successfully edited.', ['@name' => 'devel.simple']));
    $this->assertEquals(1, $this->state->get('devel.simple'));

    // Ensure that state variables that contain array can be edited and saved
    // and the new value is properly validated.
    $this->drupalGet('devel/state/edit/devel.array');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(strtr('Edit state variable: @name', ['@name' => 'devel.array']));
    $input = $this->assertSession()->fieldExists('edit-new-value');
    $this->assertFalse($input->hasAttribute('disabled'));
    $button = $this->assertSession()->buttonExists('edit-submit');
    $this->assertFalse($button->hasAttribute('disabled'));

    // Try to save an invalid yaml input.
    $edit = ['new_value' => 'devel: \'value updated'];
    $this->drupalPostForm('devel/state/edit/devel.array', $edit, 'Save');
    $this->assertSession()->pageTextContains('Invalid input:');

    $edit = ['new_value' => 'devel: \'value updated\''];
    $this->drupalPostForm('devel/state/edit/devel.array', $edit, 'Save');
    $this->assertSession()->pageTextContains(strtr('Variable @name was successfully edited.', ['@name' => 'devel.array']));
    $this->assertEquals(['devel' => 'value updated'], $this->state->get('devel.array'));

    // Ensure that state variables that contain objects cannot be edited.
    $this->drupalGet('devel/state/edit/devel.object');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(strtr('Edit state variable: @name', ['@name' => 'devel.object']));
    $this->assertSession()->pageTextContains(strtr('Only simple structures are allowed to be edited. State @name contains objects.', ['@name' => 'devel.object']));
    $this->assertSession()->fieldDisabled('edit-new-value');
    $button = $this->assertSession()->buttonExists('edit-submit');
    $this->assertTrue($button->hasAttribute('disabled'));

    // Ensure that the cancel link works as expected.
    $this->clickLink('Cancel');
    $this->assertSession()->addressEquals('devel/state');
  }

  /**
   * Checks that the passed in elements have the expected text.
   *
   * @param \Behat\Mink\Element\NodeElement[] $elements
   *   The elements for which check the text.
   * @param array $expected_elements_text
   *   The expected text for the passed in elements.
   */
  protected function assertElementsTextEquals(array $elements, array $expected_elements_text) {
    $actual_text = array_map(function (NodeElement $element) {
      return $element->getText();
    }, $elements);
    $this->assertSame($expected_elements_text, $actual_text);
  }

}
