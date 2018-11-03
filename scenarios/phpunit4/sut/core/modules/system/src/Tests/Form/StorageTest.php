<?php

namespace Drupal\system\Tests\Form;

use Drupal\simpletest\WebTestBase;

/**
 * Tests a multistep form using form storage and makes sure validation and
 * caching works right.
 *
 * The tested form puts data into the storage during the initial form
 * construction. These tests verify that there are no duplicate form
 * constructions, with and without manual form caching activated. Furthermore
 * when a validation error occurs, it makes sure that changed form element
 * values are not lost due to a wrong form rebuild.
 *
 * @group Form
 */
class StorageTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['form_test', 'dblog'];

  protected function setUp() {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser());
  }

  /**
   * Tests using the form in a usual way.
   */
  public function testForm() {
    $this->drupalGet('form_test/form-storage');
    $this->assertText('Form constructions: 1');

    $edit = ['title' => 'new', 'value' => 'value_is_set'];

    // Use form rebuilding triggered by a submit button.
    $this->drupalPostForm(NULL, $edit, 'Continue submit');
    $this->assertText('Form constructions: 2');
    $this->assertText('Form constructions: 3');

    // Reset the form to the values of the storage, using a form rebuild
    // triggered by button of type button.
    $this->drupalPostForm(NULL, ['title' => 'changed'], 'Reset');
    $this->assertFieldByName('title', 'new', 'Values have been reset.');
    // After rebuilding, the form has been cached.
    $this->assertText('Form constructions: 4');

    $this->drupalPostForm(NULL, $edit, 'Save');
    $this->assertText('Form constructions: 4');
    $this->assertText('Title: new', 'The form storage has stored the values.');
  }

  /**
   * Tests using the form after calling $form_state->setCached().
   */
  public function testFormCached() {
    $this->drupalGet('form_test/form-storage', ['query' => ['cache' => 1]]);
    $this->assertText('Form constructions: 1');

    $edit = ['title' => 'new', 'value' => 'value_is_set'];

    // Use form rebuilding triggered by a submit button.
    $this->drupalPostForm(NULL, $edit, 'Continue submit');
    // The first one is for the building of the form.
    $this->assertText('Form constructions: 2');
    // The second one is for the rebuilding of the form.
    $this->assertText('Form constructions: 3');

    // Reset the form to the values of the storage, using a form rebuild
    // triggered by button of type button.
    $this->drupalPostForm(NULL, ['title' => 'changed'], 'Reset');
    $this->assertFieldByName('title', 'new', 'Values have been reset.');
    $this->assertText('Form constructions: 4');

    $this->drupalPostForm(NULL, $edit, 'Save');
    $this->assertText('Form constructions: 4');
    $this->assertText('Title: new', 'The form storage has stored the values.');
  }

  /**
   * Tests validation when form storage is used.
   */
  public function testValidation() {
    $this->drupalPostForm('form_test/form-storage', ['title' => '', 'value' => 'value_is_set'], 'Continue submit');
    $this->assertPattern('/value_is_set/', 'The input values have been kept.');
  }

  /**
   * Tests updating cached form storage during form validation.
   *
   * If form caching is enabled and a form stores data in the form storage, then
   * the form storage also has to be updated in case of a validation error in
   * the form. This test re-uses the existing form for multi-step tests, but
   * triggers a special #element_validate handler to update the form storage
   * during form validation, while another, required element in the form
   * triggers a form validation error.
   */
  public function testCachedFormStorageValidation() {
    // Request the form with 'cache' query parameter to enable form caching.
    $this->drupalGet('form_test/form-storage', ['query' => ['cache' => 1]]);

    // Skip step 1 of the multi-step form, since the first step copies over
    // 'title' into form storage, but we want to verify that changes in the form
    // storage are updated in the cache during form validation.
    $edit = ['title' => 'foo'];
    $this->drupalPostForm(NULL, $edit, 'Continue submit');

    // In step 2, trigger a validation error for the required 'title' field, and
    // post the special 'change_title' value for the 'value' field, which
    // conditionally invokes the #element_validate handler to update the form
    // storage.
    $edit = ['title' => '', 'value' => 'change_title'];
    $this->drupalPostForm(NULL, $edit, 'Save');

    // At this point, the form storage should contain updated values, but we do
    // not see them, because the form has not been rebuilt yet due to the
    // validation error. Post again and verify that the rebuilt form contains
    // the values of the updated form storage.
    $this->drupalPostForm(NULL, ['title' => 'foo', 'value' => 'bar'], 'Save');
    $this->assertText("The thing has been changed.", 'The altered form storage value was updated in cache and taken over.');
  }

  /**
   * Verifies that form build-id is regenerated when loading an immutable form
   * from the cache.
   */
  public function testImmutableForm() {
    // Request the form with 'cache' query parameter to enable form caching.
    $this->drupalGet('form_test/form-storage', ['query' => ['cache' => 1, 'immutable' => 1]]);
    $buildIdFields = $this->xpath('//input[@name="form_build_id"]');
    $this->assertEqual(count($buildIdFields), 1, 'One form build id field on the page');
    $buildId = (string) $buildIdFields[0]['value'];

    // Trigger validation error by submitting an empty title.
    $edit = ['title' => ''];
    $this->drupalPostForm(NULL, $edit, 'Continue submit');

    // Verify that the build-id did change.
    $this->assertNoFieldByName('form_build_id', $buildId, 'Build id changes when form validation fails');

    // Retrieve the new build-id.
    $buildIdFields = $this->xpath('//input[@name="form_build_id"]');
    $this->assertEqual(count($buildIdFields), 1, 'One form build id field on the page');
    $buildId = (string) $buildIdFields[0]['value'];

    // Trigger validation error by again submitting an empty title.
    $edit = ['title' => ''];
    $this->drupalPostForm(NULL, $edit, 'Continue submit');

    // Verify that the build-id does not change the second time.
    $this->assertFieldByName('form_build_id', $buildId, 'Build id remains the same when form validation fails subsequently');
  }

  /**
   * Verify that existing contrib code cannot overwrite immutable form state.
   */
  public function testImmutableFormLegacyProtection() {
    $this->drupalGet('form_test/form-storage', ['query' => ['cache' => 1, 'immutable' => 1]]);
    $build_id_fields = $this->xpath('//input[@name="form_build_id"]');
    $this->assertEqual(count($build_id_fields), 1, 'One form build id field on the page');
    $build_id = (string) $build_id_fields[0]['value'];

    // Try to poison the form cache.
    $original = $this->drupalGetAjax('form-test/form-storage-legacy/' . $build_id);
    $this->assertEqual($original['form']['#build_id_old'], $build_id, 'Original build_id was recorded');
    $this->assertNotEqual($original['form']['#build_id'], $build_id, 'New build_id was generated');

    // Assert that a watchdog message was logged by
    // \Drupal::formBuilder()->setCache().
    $status = (bool) db_query_range('SELECT 1 FROM {watchdog} WHERE message = :message', 0, 1, [':message' => 'Form build-id mismatch detected while attempting to store a form in the cache.']);
    $this->assert($status, 'A watchdog message was logged by \Drupal::formBuilder()->setCache');

    // Ensure that the form state was not poisoned by the preceding call.
    $original = $this->drupalGetAjax('form-test/form-storage-legacy/' . $build_id);
    $this->assertEqual($original['form']['#build_id_old'], $build_id, 'Original build_id was recorded');
    $this->assertNotEqual($original['form']['#build_id'], $build_id, 'New build_id was generated');
    $this->assert(empty($original['form']['#poisoned']), 'Original form structure was preserved');
    $this->assert(empty($original['form_state']['poisoned']), 'Original form state was preserved');
  }

}
