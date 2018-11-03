<?php

namespace Drupal\file\Tests;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_ui\Tests\FieldUiTestTrait;
use Drupal\user\RoleInterface;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Tests the file field widget, single and multi-valued, with and without AJAX,
 * with public and private files.
 *
 * @group file
 */
class FileFieldWidgetTest extends FileFieldTestBase {

  use CommentTestTrait;
  use FieldUiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalPlaceBlock('system_breadcrumb_block');
  }

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['comment', 'block'];

  /**
   * Creates a temporary file, for a specific user.
   *
   * @param string $data
   *   A string containing the contents of the file.
   * @param \Drupal\user\UserInterface $user
   *   The user of the file owner.
   *
   * @return \Drupal\file\FileInterface
   *   A file object, or FALSE on error.
   */
  protected function createTemporaryFile($data, UserInterface $user = NULL) {
    $file = file_save_data($data, NULL, NULL);

    if ($file) {
      if ($user) {
        $file->setOwner($user);
      }
      else {
        $file->setOwner($this->adminUser);
      }
      // Change the file status to be temporary.
      $file->setTemporary();
      // Save the changes.
      $file->save();
    }

    return $file;
  }

  /**
   * Tests upload and remove buttons for a single-valued File field.
   */
  public function testSingleValuedWidget() {
    $node_storage = $this->container->get('entity.manager')->getStorage('node');
    $type_name = 'article';
    $field_name = strtolower($this->randomMachineName());
    $this->createFileField($field_name, 'node', $type_name);

    $test_file = $this->getTestFile('text');

    foreach (['nojs', 'js'] as $type) {
      // Create a new node with the uploaded file and ensure it got uploaded
      // successfully.
      // @todo This only tests a 'nojs' submission, because drupalPostAjaxForm()
      //   does not yet support file uploads.
      $nid = $this->uploadNodeFile($test_file, $field_name, $type_name);
      $node_storage->resetCache([$nid]);
      $node = $node_storage->load($nid);
      $node_file = File::load($node->{$field_name}->target_id);
      $this->assertFileExists($node_file, 'New file saved to disk on node creation.');

      // Ensure the file can be downloaded.
      $this->drupalGet(file_create_url($node_file->getFileUri()));
      $this->assertResponse(200, 'Confirmed that the generated URL is correct by downloading the shipped file.');

      // Ensure the edit page has a remove button instead of an upload button.
      $this->drupalGet("node/$nid/edit");
      $this->assertNoFieldByXPath('//input[@type="submit"]', t('Upload'), 'Node with file does not display the "Upload" button.');
      $this->assertFieldByXpath('//input[@type="submit"]', t('Remove'), 'Node with file displays the "Remove" button.');

      // "Click" the remove button (emulating either a nojs or js submission).
      switch ($type) {
        case 'nojs':
          $this->drupalPostForm(NULL, [], t('Remove'));
          break;
        case 'js':
          $button = $this->xpath('//input[@type="submit" and @value="' . t('Remove') . '"]');
          $this->drupalPostAjaxForm(NULL, [], [(string) $button[0]['name'] => (string) $button[0]['value']]);
          break;
      }

      // Ensure the page now has an upload button instead of a remove button.
      $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'After clicking the "Remove" button, it is no longer displayed.');
      $this->assertFieldByXpath('//input[@type="submit"]', t('Upload'), 'After clicking the "Remove" button, the "Upload" button is displayed.');
      // Test label has correct 'for' attribute.
      $input = $this->xpath('//input[@name="files[' . $field_name . '_0]"]');
      $label = $this->xpath('//label[@for="' . (string) $input[0]['id'] . '"]');
      $this->assertTrue(isset($label[0]), 'Label for upload found.');

      // Save the node and ensure it does not have the file.
      $this->drupalPostForm(NULL, [], t('Save'));
      $node_storage->resetCache([$nid]);
      $node = $node_storage->load($nid);
      $this->assertTrue(empty($node->{$field_name}->target_id), 'File was successfully removed from the node.');
    }
  }

  /**
   * Tests upload and remove buttons for multiple multi-valued File fields.
   */
  public function testMultiValuedWidget() {
    $node_storage = $this->container->get('entity.manager')->getStorage('node');
    $type_name = 'article';
    // Use explicit names instead of random names for those fields, because of a
    // bug in drupalPostForm() with multiple file uploads in one form, where the
    // order of uploads depends on the order in which the upload elements are
    // added to the $form (which, in the current implementation of
    // FileStorage::listAll(), comes down to the alphabetical order on field
    // names).
    $field_name = 'test_file_field_1';
    $field_name2 = 'test_file_field_2';
    $cardinality = 3;
    $this->createFileField($field_name, 'node', $type_name, ['cardinality' => $cardinality]);
    $this->createFileField($field_name2, 'node', $type_name, ['cardinality' => $cardinality]);

    $test_file = $this->getTestFile('text');

    foreach (['nojs', 'js'] as $type) {
      // Visit the node creation form, and upload 3 files for each field. Since
      // the field has cardinality of 3, ensure the "Upload" button is displayed
      // until after the 3rd file, and after that, isn't displayed. Because
      // SimpleTest triggers the last button with a given name, so upload to the
      // second field first.
      // @todo This is only testing a non-Ajax upload, because drupalPostAjaxForm()
      //   does not yet emulate jQuery's file upload.
      //
      $this->drupalGet("node/add/$type_name");
      foreach ([$field_name2, $field_name] as $each_field_name) {
        for ($delta = 0; $delta < 3; $delta++) {
          $edit = ['files[' . $each_field_name . '_' . $delta . '][]' => \Drupal::service('file_system')->realpath($test_file->getFileUri())];
          // If the Upload button doesn't exist, drupalPostForm() will automatically
          // fail with an assertion message.
          $this->drupalPostForm(NULL, $edit, t('Upload'));
        }
      }
      $this->assertNoFieldByXpath('//input[@type="submit"]', t('Upload'), 'After uploading 3 files for each field, the "Upload" button is no longer displayed.');

      $num_expected_remove_buttons = 6;

      foreach ([$field_name, $field_name2] as $current_field_name) {
        // How many uploaded files for the current field are remaining.
        $remaining = 3;
        // Test clicking each "Remove" button. For extra robustness, test them out
        // of sequential order. They are 0-indexed, and get renumbered after each
        // iteration, so array(1, 1, 0) means:
        // - First remove the 2nd file.
        // - Then remove what is then the 2nd file (was originally the 3rd file).
        // - Then remove the first file.
        foreach ([1, 1, 0] as $delta) {
          // Ensure we have the expected number of Remove buttons, and that they
          // are numbered sequentially.
          $buttons = $this->xpath('//input[@type="submit" and @value="Remove"]');
          $this->assertTrue(is_array($buttons) && count($buttons) === $num_expected_remove_buttons, format_string('There are %n "Remove" buttons displayed (JSMode=%type).', ['%n' => $num_expected_remove_buttons, '%type' => $type]));
          foreach ($buttons as $i => $button) {
            $key = $i >= $remaining ? $i - $remaining : $i;
            $check_field_name = $field_name2;
            if ($current_field_name == $field_name && $i < $remaining) {
              $check_field_name = $field_name;
            }

            $this->assertIdentical((string) $button['name'], $check_field_name . '_' . $key . '_remove_button');
          }

          // "Click" the remove button (emulating either a nojs or js submission).
          $button_name = $current_field_name . '_' . $delta . '_remove_button';
          switch ($type) {
            case 'nojs':
              // drupalPostForm() takes a $submit parameter that is the value of the
              // button whose click we want to emulate. Since we have multiple
              // buttons with the value "Remove", and want to control which one we
              // use, we change the value of the other ones to something else.
              // Since non-clicked buttons aren't included in the submitted POST
              // data, and since drupalPostForm() will result in $this being updated
              // with a newly rebuilt form, this doesn't cause problems.
              foreach ($buttons as $button) {
                if ($button['name'] != $button_name) {
                  $button['value'] = 'DUMMY';
                }
              }
              $this->drupalPostForm(NULL, [], t('Remove'));
              break;
            case 'js':
              // drupalPostAjaxForm() lets us target the button precisely, so we don't
              // require the workaround used above for nojs.
              $this->drupalPostAjaxForm(NULL, [], [$button_name => t('Remove')]);
              break;
          }
          $num_expected_remove_buttons--;
          $remaining--;

          // Ensure an "Upload" button for the current field is displayed with the
          // correct name.
          $upload_button_name = $current_field_name . '_' . $remaining . '_upload_button';
          $buttons = $this->xpath('//input[@type="submit" and @value="Upload" and @name=:name]', [':name' => $upload_button_name]);
          $this->assertTrue(is_array($buttons) && count($buttons) == 1, format_string('The upload button is displayed with the correct name (JSMode=%type).', ['%type' => $type]));

          // Ensure only at most one button per field is displayed.
          $buttons = $this->xpath('//input[@type="submit" and @value="Upload"]');
          $expected = $current_field_name == $field_name ? 1 : 2;
          $this->assertTrue(is_array($buttons) && count($buttons) == $expected, format_string('After removing a file, only one "Upload" button for each possible field is displayed (JSMode=%type).', ['%type' => $type]));
        }
      }

      // Ensure the page now has no Remove buttons.
      $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), format_string('After removing all files, there is no "Remove" button displayed (JSMode=%type).', ['%type' => $type]));

      // Save the node and ensure it does not have any files.
      $this->drupalPostForm(NULL, ['title[0][value]' => $this->randomMachineName()], t('Save'));
      preg_match('/node\/([0-9]+)/', $this->getUrl(), $matches);
      $nid = $matches[1];
      $node_storage->resetCache([$nid]);
      $node = $node_storage->load($nid);
      $this->assertTrue(empty($node->{$field_name}->target_id), 'Node was successfully saved without any files.');
    }

    $upload_files_node_creation = [$test_file, $test_file];
    // Try to upload multiple files, but fewer than the maximum.
    $nid = $this->uploadNodeFiles($upload_files_node_creation, $field_name, $type_name);
    $node_storage->resetCache([$nid]);
    $node = $node_storage->load($nid);
    $this->assertEqual(count($node->{$field_name}), count($upload_files_node_creation), 'Node was successfully saved with mulitple files.');

    // Try to upload more files than allowed on revision.
    $upload_files_node_revision = [$test_file, $test_file, $test_file, $test_file];
    $this->uploadNodeFiles($upload_files_node_revision, $field_name, $nid, 1);
    $args = [
      '%field' => $field_name,
      '@max' => $cardinality,
      '@count' => count($upload_files_node_creation) + count($upload_files_node_revision),
      '%list' => implode(', ', array_fill(0, 3, $test_file->getFilename())),
    ];
    $this->assertRaw(t('Field %field can only hold @max values but there were @count uploaded. The following files have been omitted as a result: %list.', $args));
    $node_storage->resetCache([$nid]);
    $node = $node_storage->load($nid);
    $this->assertEqual(count($node->{$field_name}), $cardinality, 'More files than allowed could not be saved to node.');

    // Try to upload exactly the allowed number of files on revision. Create an
    // empty node first, to fill it in its first revision.
    $node = $this->drupalCreateNode([
      'type' => $type_name,
    ]);
    $this->uploadNodeFile($test_file, $field_name, $node->id(), 1);
    $node_storage->resetCache([$nid]);
    $node = $node_storage->load($nid);
    $this->assertEqual(count($node->{$field_name}), $cardinality, 'Node was successfully revised to maximum number of files.');

    // Try to upload exactly the allowed number of files, new node.
    $upload_files = array_fill(0, $cardinality, $test_file);
    $nid = $this->uploadNodeFiles($upload_files, $field_name, $type_name);
    $node_storage->resetCache([$nid]);
    $node = $node_storage->load($nid);
    $this->assertEqual(count($node->{$field_name}), $cardinality, 'Node was successfully saved with maximum number of files.');

    // Try to upload more files than allowed, new node.
    $upload_files[] = $test_file;
    $this->uploadNodeFiles($upload_files, $field_name, $type_name);

    $args = [
      '%field' => $field_name,
      '@max' => $cardinality,
      '@count' => count($upload_files),
      '%list' => $test_file->getFileName(),
    ];
    $this->assertRaw(t('Field %field can only hold @max values but there were @count uploaded. The following files have been omitted as a result: %list.', $args));
  }

  /**
   * Tests a file field with a "Private files" upload destination setting.
   */
  public function testPrivateFileSetting() {
    $node_storage = $this->container->get('entity.manager')->getStorage('node');
    // Grant the admin user required permissions.
    user_role_grant_permissions($this->adminUser->roles[0]->target_id, ['administer node fields']);

    $type_name = 'article';
    $field_name = strtolower($this->randomMachineName());
    $this->createFileField($field_name, 'node', $type_name);
    $field = FieldConfig::loadByName('node', $type_name, $field_name);
    $field_id = $field->id();

    $test_file = $this->getTestFile('text');

    // Change the field setting to make its files private, and upload a file.
    $edit = ['settings[uri_scheme]' => 'private'];
    $this->drupalPostForm("admin/structure/types/manage/$type_name/fields/$field_id/storage", $edit, t('Save field settings'));
    $nid = $this->uploadNodeFile($test_file, $field_name, $type_name);
    $node_storage->resetCache([$nid]);
    $node = $node_storage->load($nid);
    $node_file = File::load($node->{$field_name}->target_id);
    $this->assertFileExists($node_file, 'New file saved to disk on node creation.');

    // Ensure the private file is available to the user who uploaded it.
    $this->drupalGet(file_create_url($node_file->getFileUri()));
    $this->assertResponse(200, 'Confirmed that the generated URL is correct by downloading the shipped file.');

    // Ensure we can't change 'uri_scheme' field settings while there are some
    // entities with uploaded files.
    $this->drupalGet("admin/structure/types/manage/$type_name/fields/$field_id/storage");
    $this->assertFieldByXpath('//input[@id="edit-settings-uri-scheme-public" and @disabled="disabled"]', 'public', 'Upload destination setting disabled.');

    // Delete node and confirm that setting could be changed.
    $node->delete();
    $this->drupalGet("admin/structure/types/manage/$type_name/fields/$field_id/storage");
    $this->assertFieldByXpath('//input[@id="edit-settings-uri-scheme-public" and not(@disabled)]', 'public', 'Upload destination setting enabled.');
  }

  /**
   * Tests that download restrictions on private files work on comments.
   */
  public function testPrivateFileComment() {
    $user = $this->drupalCreateUser(['access comments']);

    // Grant the admin user required comment permissions.
    $roles = $this->adminUser->getRoles();
    user_role_grant_permissions($roles[1], ['administer comment fields', 'administer comments']);

    // Revoke access comments permission from anon user, grant post to
    // authenticated.
    user_role_revoke_permissions(RoleInterface::ANONYMOUS_ID, ['access comments']);
    user_role_grant_permissions(RoleInterface::AUTHENTICATED_ID, ['post comments', 'skip comment approval']);

    // Create a new field.
    $this->addDefaultCommentField('node', 'article');

    $name = strtolower($this->randomMachineName());
    $label = $this->randomMachineName();
    $storage_edit = ['settings[uri_scheme]' => 'private'];
    $this->fieldUIAddNewField('admin/structure/comment/manage/comment', $name, $label, 'file', $storage_edit);

    // Manually clear cache on the tester side.
    \Drupal::entityManager()->clearCachedFieldDefinitions();

    // Create node.
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
    ];
    $this->drupalPostForm('node/add/article', $edit, t('Save'));
    $node = $this->drupalGetNodeByTitle($edit['title[0][value]']);

    // Add a comment with a file.
    $text_file = $this->getTestFile('text');
    $edit = [
      'files[field_' . $name . '_' . 0 . ']' => \Drupal::service('file_system')->realpath($text_file->getFileUri()),
      'comment_body[0][value]' => $comment_body = $this->randomMachineName(),
    ];
    $this->drupalPostForm('node/' . $node->id(), $edit, t('Save'));

    // Get the comment ID.
    preg_match('/comment-([0-9]+)/', $this->getUrl(), $matches);
    $cid = $matches[1];

    // Log in as normal user.
    $this->drupalLogin($user);

    $comment = Comment::load($cid);
    $comment_file = $comment->{'field_' . $name}->entity;
    $this->assertFileExists($comment_file, 'New file saved to disk on node creation.');
    // Test authenticated file download.
    $url = file_create_url($comment_file->getFileUri());
    $this->assertNotEqual($url, NULL, 'Confirmed that the URL is valid');
    $this->drupalGet(file_create_url($comment_file->getFileUri()));
    $this->assertResponse(200, 'Confirmed that the generated URL is correct by downloading the shipped file.');

    // Test anonymous file download.
    $this->drupalLogout();
    $this->drupalGet(file_create_url($comment_file->getFileUri()));
    $this->assertResponse(403, 'Confirmed that access is denied for the file without the needed permission.');

    // Unpublishes node.
    $this->drupalLogin($this->adminUser);
    $edit = ['status[value]' => FALSE];
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save'));

    // Ensures normal user can no longer download the file.
    $this->drupalLogin($user);
    $this->drupalGet(file_create_url($comment_file->getFileUri()));
    $this->assertResponse(403, 'Confirmed that access is denied for the file without the needed permission.');
  }

  /**
   * Tests validation with the Upload button.
   */
  public function testWidgetValidation() {
    $type_name = 'article';
    $field_name = strtolower($this->randomMachineName());
    $this->createFileField($field_name, 'node', $type_name);
    $this->updateFileField($field_name, $type_name, ['file_extensions' => 'txt']);

    foreach (['nojs', 'js'] as $type) {
      // Create node and prepare files for upload.
      $node = $this->drupalCreateNode(['type' => 'article']);
      $nid = $node->id();
      $this->drupalGet("node/$nid/edit");
      $test_file_text = $this->getTestFile('text');
      $test_file_image = $this->getTestFile('image');
      $name = 'files[' . $field_name . '_0]';

      // Upload file with incorrect extension, check for validation error.
      $edit[$name] = \Drupal::service('file_system')->realpath($test_file_image->getFileUri());
      switch ($type) {
        case 'nojs':
          $this->drupalPostForm(NULL, $edit, t('Upload'));
          break;
        case 'js':
          $button = $this->xpath('//input[@type="submit" and @value="' . t('Upload') . '"]');
          $this->drupalPostAjaxForm(NULL, $edit, [(string) $button[0]['name'] => (string) $button[0]['value']]);
          break;
      }
      $error_message = t('Only files with the following extensions are allowed: %files-allowed.', ['%files-allowed' => 'txt']);
      $this->assertRaw($error_message, t('Validation error when file with wrong extension uploaded (JSMode=%type).', ['%type' => $type]));

      // Upload file with correct extension, check that error message is removed.
      $edit[$name] = \Drupal::service('file_system')->realpath($test_file_text->getFileUri());
      switch ($type) {
        case 'nojs':
          $this->drupalPostForm(NULL, $edit, t('Upload'));
          break;
        case 'js':
          $button = $this->xpath('//input[@type="submit" and @value="' . t('Upload') . '"]');
          $this->drupalPostAjaxForm(NULL, $edit, [(string) $button[0]['name'] => (string) $button[0]['value']]);
          break;
      }
      $this->assertNoRaw($error_message, t('Validation error removed when file with correct extension uploaded (JSMode=%type).', ['%type' => $type]));
    }
  }

  /**
   * Tests file widget element.
   */
  public function testWidgetElement() {
    $field_name = mb_strtolower($this->randomMachineName());
    $html_name = str_replace('_', '-', $field_name);
    $this->createFileField($field_name, 'node', 'article', ['cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED]);
    $file = $this->getTestFile('text');
    $xpath = "//details[@data-drupal-selector='edit-$html_name']/div[@class='details-wrapper']/table";

    $this->drupalGet('node/add/article');

    $elements = $this->xpath($xpath);

    // If the field has no item, the table should not be visible.
    $this->assertIdentical(count($elements), 0);

    // Upload a file.
    $edit['files[' . $field_name . '_0][]'] = $this->container->get('file_system')->realpath($file->getFileUri());
    $this->drupalPostAjaxForm(NULL, $edit, "{$field_name}_0_upload_button");

    $elements = $this->xpath($xpath);

    // If the field has at least a item, the table should be visible.
    $this->assertIdentical(count($elements), 1);

    // Test for AJAX error when using progress bar on file field widget
    $key = $this->randomMachineName();
    $this->drupalPost('file/progress/' . $key, 'application/json', []);
    $this->assertNoResponse(500, t('No AJAX error when using progress bar on file field widget'));
    $this->assertText('Starting upload...');
  }

  /**
   * Tests exploiting the temporary file removal of another user using fid.
   */
  public function testTemporaryFileRemovalExploit() {
    // Create a victim user.
    $victim_user = $this->drupalCreateUser();

    // Create an attacker user.
    $attacker_user = $this->drupalCreateUser([
      'access content',
      'create article content',
      'edit any article content',
    ]);

    // Log in as the attacker user.
    $this->drupalLogin($attacker_user);

    // Perform tests using the newly created users.
    $this->doTestTemporaryFileRemovalExploit($victim_user, $attacker_user);
  }

  /**
   * Tests exploiting the temporary file removal for anonymous users using fid.
   */
  public function testTemporaryFileRemovalExploitAnonymous() {
    // Set up an anonymous victim user.
    $victim_user = User::getAnonymousUser();

    // Set up an anonymous attacker user.
    $attacker_user = User::getAnonymousUser();

    // Set up permissions for anonymous attacker user.
    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'access content' => TRUE,
      'create article content' => TRUE,
      'edit any article content' => TRUE,
    ]);

    // Log out so as to be the anonymous attacker user.
    $this->drupalLogout();

    // Perform tests using the newly set up anonymous users.
    $this->doTestTemporaryFileRemovalExploit($victim_user, $attacker_user);
  }

  /**
   * Helper for testing exploiting the temporary file removal using fid.
   *
   * @param \Drupal\user\UserInterface $victim_user
   *   The victim user.
   * @param \Drupal\user\UserInterface $attacker_user
   *   The attacker user.
   */
  protected function doTestTemporaryFileRemovalExploit(UserInterface $victim_user, UserInterface $attacker_user) {
    $type_name = 'article';
    $field_name = 'test_file_field';
    $this->createFileField($field_name, 'node', $type_name);

    $test_file = $this->getTestFile('text');
    foreach (['nojs', 'js'] as $type) {
      // Create a temporary file owned by the victim user. This will be as if
      // they had uploaded the file, but not saved the node they were editing
      // or creating.
      $victim_tmp_file = $this->createTemporaryFile('some text', $victim_user);
      $victim_tmp_file = File::load($victim_tmp_file->id());
      $this->assertTrue($victim_tmp_file->isTemporary(), 'New file saved to disk is temporary.');
      $this->assertFalse(empty($victim_tmp_file->id()), 'New file has an fid.');
      $this->assertEqual($victim_user->id(), $victim_tmp_file->getOwnerId(), 'New file belongs to the victim.');

      // Have attacker create a new node with a different uploaded file and
      // ensure it got uploaded successfully.
      $edit = [
        'title[0][value]' => $type . '-title' ,
      ];

      // Attach a file to a node.
      $edit['files[' . $field_name . '_0]'] = $this->container->get('file_system')->realpath($test_file->getFileUri());
      $this->drupalPostForm(Url::fromRoute('node.add', ['node_type' => $type_name]), $edit, t('Save'));
      $node = $this->drupalGetNodeByTitle($edit['title[0][value]']);

      /** @var \Drupal\file\FileInterface $node_file */
      $node_file = File::load($node->{$field_name}->target_id);
      $this->assertFileExists($node_file, 'A file was saved to disk on node creation');
      $this->assertEqual($attacker_user->id(), $node_file->getOwnerId(), 'New file belongs to the attacker.');

      // Ensure the file can be downloaded.
      $this->drupalGet(file_create_url($node_file->getFileUri()));
      $this->assertResponse(200, 'Confirmed that the generated URL is correct by downloading the shipped file.');

      // "Click" the remove button (emulating either a nojs or js submission).
      // In this POST request, the attacker "guesses" the fid of the victim's
      // temporary file and uses that to remove this file.
      $this->drupalGet($node->toUrl('edit-form'));
      switch ($type) {
        case 'nojs':
          $this->drupalPostForm(NULL, [$field_name . '[0][fids]' => (string) $victim_tmp_file->id()], 'Remove');
          break;

        case 'js':
          $this->drupalPostAjaxForm(NULL, [$field_name . '[0][fids]' => (string) $victim_tmp_file->id()], ["{$field_name}_0_remove_button" => 'Remove']);
          break;
      }

      // The victim's temporary file should not be removed by the attacker's
      // POST request.
      $this->assertFileExists($victim_tmp_file);
    }
  }

}
