<?php

namespace Drupal\Tests\file\Functional;

use Drupal\file\Entity\File;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests the _file_save_upload_from_form() function.
 *
 * @group file
 *
 * @see _file_save_upload_from_form()
 */
class SaveUploadFormTest extends FileManagedTestBase {

  use TestFileCreationTrait {
    getTestFiles as drupalGetTestFiles;
  }

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['dblog'];

  /**
   * An image file path for uploading.
   *
   * @var \Drupal\file\FileInterface
   */
  protected $image;

  /**
   * A PHP file path for upload security testing.
   */
  protected $phpfile;

  /**
   * The largest file id when the test starts.
   */
  protected $maxFidBefore;

  /**
   * Extension of the image filename.
   *
   * @var string
   */
  protected $imageExtension;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $account = $this->drupalCreateUser(['access site reports']);
    $this->drupalLogin($account);

    $image_files = $this->drupalGetTestFiles('image');
    $this->image = File::create((array) current($image_files));

    list(, $this->imageExtension) = explode('.', $this->image->getFilename());
    $this->assertTrue(is_file($this->image->getFileUri()), "The image file we're going to upload exists.");

    $this->phpfile = current($this->drupalGetTestFiles('php'));
    $this->assertTrue(is_file($this->phpfile->uri), 'The PHP file we are going to upload exists.');

    $this->maxFidBefore = db_query('SELECT MAX(fid) AS fid FROM {file_managed}')->fetchField();

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    // Upload with replace to guarantee there's something there.
    $edit = [
      'file_test_replace' => FILE_EXISTS_REPLACE,
      'files[file_test_upload][]' => $file_system->realpath($this->image->getFileUri()),
    ];
    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called then clean out the hook
    // counters.
    $this->assertFileHooksCalled(['validate', 'insert']);
    file_test_reset();
  }

  /**
   * Tests the _file_save_upload_from_form() function.
   */
  public function testNormal() {
    $max_fid_after = db_query('SELECT MAX(fid) AS fid FROM {file_managed}')->fetchField();
    $this->assertTrue($max_fid_after > $this->maxFidBefore, 'A new file was created.');
    $file1 = File::load($max_fid_after);
    $this->assertTrue($file1, 'Loaded the file.');
    // MIME type of the uploaded image may be either image/jpeg or image/png.
    $this->assertEqual(substr($file1->getMimeType(), 0, 5), 'image', 'A MIME type was set.');

    // Reset the hook counters to get rid of the 'load' we just called.
    file_test_reset();

    // Upload a second file.
    $image2 = current($this->drupalGetTestFiles('image'));
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $edit = ['files[file_test_upload][]' => $file_system->realpath($image2->uri)];
    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('You WIN!'));
    $max_fid_after = db_query('SELECT MAX(fid) AS fid FROM {file_managed}')->fetchField();

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(['validate', 'insert']);

    $file2 = File::load($max_fid_after);
    $this->assertTrue($file2, 'Loaded the file');
    // MIME type of the uploaded image may be either image/jpeg or image/png.
    $this->assertEqual(substr($file2->getMimeType(), 0, 5), 'image', 'A MIME type was set.');

    // Load both files using File::loadMultiple().
    $files = File::loadMultiple([$file1->id(), $file2->id()]);
    $this->assertTrue(isset($files[$file1->id()]), 'File was loaded successfully');
    $this->assertTrue(isset($files[$file2->id()]), 'File was loaded successfully');

    // Upload a third file to a subdirectory.
    $image3 = current($this->drupalGetTestFiles('image'));
    $image3_realpath = $file_system->realpath($image3->uri);
    $dir = $this->randomMachineName();
    $edit = [
      'files[file_test_upload][]' => $image3_realpath,
      'file_subdir' => $dir,
    ];
    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('You WIN!'));
    $this->assertTrue(is_file('temporary://' . $dir . '/' . trim(drupal_basename($image3_realpath))));
  }

  /**
   * Tests extension handling.
   */
  public function testHandleExtension() {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    // The file being tested is a .gif which is in the default safe list
    // of extensions to allow when the extension validator isn't used. This is
    // implicitly tested at the testNormal() test. Here we tell
    // _file_save_upload_from_form() to only allow ".foo".
    $extensions = 'foo';
    $edit = [
      'file_test_replace' => FILE_EXISTS_REPLACE,
      'files[file_test_upload][]' => $file_system->realpath($this->image->getFileUri()),
      'extensions' => $extensions,
    ];

    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $message = t('Only files with the following extensions are allowed:') . ' <em class="placeholder">' . $extensions . '</em>';
    $this->assertRaw($message, 'Cannot upload a disallowed extension');
    $this->assertRaw(t('Epic upload FAIL!'), 'Found the failure message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(['validate']);

    // Reset the hook counters.
    file_test_reset();

    $extensions = 'foo ' . $this->imageExtension;
    // Now tell _file_save_upload_from_form() to allow the extension of our test image.
    $edit = [
      'file_test_replace' => FILE_EXISTS_REPLACE,
      'files[file_test_upload][]' => $file_system->realpath($this->image->getFileUri()),
      'extensions' => $extensions,
    ];

    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertNoRaw(t('Only files with the following extensions are allowed:'), 'Can upload an allowed extension.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(['validate', 'load', 'update']);

    // Reset the hook counters.
    file_test_reset();

    // Now tell _file_save_upload_from_form() to allow any extension.
    $edit = [
      'file_test_replace' => FILE_EXISTS_REPLACE,
      'files[file_test_upload][]' => $file_system->realpath($this->image->getFileUri()),
      'allow_all_extensions' => TRUE,
    ];
    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertNoRaw(t('Only files with the following extensions are allowed:'), 'Can upload any extension.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(['validate', 'load', 'update']);
  }

  /**
   * Tests dangerous file handling.
   */
  public function testHandleDangerousFile() {
    $config = $this->config('system.file');
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    // Allow the .php extension and make sure it gets renamed to .txt for
    // safety. Also check to make sure its MIME type was changed.
    $edit = [
      'file_test_replace' => FILE_EXISTS_REPLACE,
      'files[file_test_upload][]' => $file_system->realpath($this->phpfile->uri),
      'is_image_file' => FALSE,
      'extensions' => 'php',
    ];

    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $message = t('For security reasons, your upload has been renamed to') . ' <em class="placeholder">' . $this->phpfile->filename . '.txt' . '</em>';
    $this->assertRaw($message, 'Dangerous file was renamed.');
    $this->assertRaw(t('File MIME type is text/plain.'), "Dangerous file's MIME type was changed.");
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(['validate', 'insert']);

    // Ensure dangerous files are not renamed when insecure uploads is TRUE.
    // Turn on insecure uploads.
    $config->set('allow_insecure_uploads', 1)->save();
    // Reset the hook counters.
    file_test_reset();

    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertNoRaw(t('For security reasons, your upload has been renamed'), 'Found no security message.');
    $this->assertRaw(t('File name is @filename', ['@filename' => $this->phpfile->filename]), 'Dangerous file was not renamed when insecure uploads is TRUE.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(['validate', 'insert']);

    // Turn off insecure uploads.
    $config->set('allow_insecure_uploads', 0)->save();
  }

  /**
   * Tests file munge handling.
   */
  public function testHandleFileMunge() {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    // Ensure insecure uploads are disabled for this test.
    $this->config('system.file')->set('allow_insecure_uploads', 0)->save();
    $this->image = file_move($this->image, $this->image->getFileUri() . '.foo.' . $this->imageExtension);

    // Reset the hook counters to get rid of the 'move' we just called.
    file_test_reset();

    $extensions = $this->imageExtension;
    $edit = [
      'files[file_test_upload][]' => $file_system->realpath($this->image->getFileUri()),
      'extensions' => $extensions,
    ];

    $munged_filename = $this->image->getFilename();
    $munged_filename = substr($munged_filename, 0, strrpos($munged_filename, '.'));
    $munged_filename .= '_.' . $this->imageExtension;

    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('For security reasons, your upload has been renamed'), 'Found security message.');
    $this->assertRaw(t('File name is @filename', ['@filename' => $munged_filename]), 'File was successfully munged.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(['validate', 'insert']);

    // Ensure we don't munge files if we're allowing any extension.
    // Reset the hook counters.
    file_test_reset();

    $edit = [
      'files[file_test_upload][]' => $file_system->realpath($this->image->getFileUri()),
      'allow_all_extensions' => TRUE,
    ];

    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertNoRaw(t('For security reasons, your upload has been renamed'), 'Found no security message.');
    $this->assertRaw(t('File name is @filename', ['@filename' => $this->image->getFilename()]), 'File was not munged when allowing any extension.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(['validate', 'insert']);
  }

  /**
   * Tests renaming when uploading over a file that already exists.
   */
  public function testExistingRename() {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $edit = [
      'file_test_replace' => FILE_EXISTS_RENAME,
      'files[file_test_upload][]' => $file_system->realpath($this->image->getFileUri()),
    ];
    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(['validate', 'insert']);
  }

  /**
   * Tests replacement when uploading over a file that already exists.
   */
  public function testExistingReplace() {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $edit = [
      'file_test_replace' => FILE_EXISTS_REPLACE,
      'files[file_test_upload][]' => $file_system->realpath($this->image->getFileUri()),
    ];
    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(['validate', 'load', 'update']);
  }

  /**
   * Tests for failure when uploading over a file that already exists.
   */
  public function testExistingError() {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $edit = [
      'file_test_replace' => FILE_EXISTS_ERROR,
      'files[file_test_upload][]' => $file_system->realpath($this->image->getFileUri()),
    ];
    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('Epic upload FAIL!'), 'Found the failure message.');

    // Check that the no hooks were called while failing.
    $this->assertFileHooksCalled([]);
  }

  /**
   * Tests for no failures when not uploading a file.
   */
  public function testNoUpload() {
    $this->drupalPostForm('file-test/save_upload_from_form_test', [], t('Submit'));
    $this->assertNoRaw(t('Epic upload FAIL!'), 'Failure message not found.');
  }

  /**
   * Tests for log entry on failing destination.
   */
  public function testDrupalMovingUploadedFileError() {
    // Create a directory and make it not writable.
    $test_directory = 'test_drupal_move_uploaded_file_fail';
    drupal_mkdir('temporary://' . $test_directory, 0000);
    $this->assertTrue(is_dir('temporary://' . $test_directory));

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $edit = [
      'file_subdir' => $test_directory,
      'files[file_test_upload][]' => $file_system->realpath($this->image->getFileUri()),
    ];

    \Drupal::state()->set('file_test.disable_error_collection', TRUE);
    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('File upload error. Could not move uploaded file.'), 'Found the failure message.');
    $this->assertRaw(t('Epic upload FAIL!'), 'Found the failure message.');

    // Uploading failed. Now check the log.
    $this->drupalGet('admin/reports/dblog');
    $this->assertResponse(200);
    $this->assertRaw(t('Upload error. Could not move uploaded file @file to destination @destination.', [
      '@file' => $this->image->getFilename(),
      '@destination' => 'temporary://' . $test_directory . '/' . $this->image->getFilename(),
    ]), 'Found upload error log entry.');
  }

  /**
   * Tests that form validation does not change error messages.
   */
  public function testErrorMessagesAreNotChanged() {
    $error = 'An error message set before _file_save_upload_from_form()';

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $edit = [
      'files[file_test_upload][]' => $file_system->realpath($this->image->getFileUri()),
      'error_message' => $error,
    ];
    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Ensure the expected error message is present and the counts before and
    // after calling _file_save_upload_from_form() are correct.
    $this->assertText($error);
    $this->assertRaw('Number of error messages before _file_save_upload_from_form(): 1');
    $this->assertRaw('Number of error messages after _file_save_upload_from_form(): 1');

    // Test that error messages are preserved when an error occurs.
    $edit = [
      'files[file_test_upload][]' => $file_system->realpath($this->image->getFileUri()),
      'error_message' => $error,
      'extensions' => 'foo',
    ];
    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('Epic upload FAIL!'), 'Found the failure message.');

    // Ensure the expected error message is present and the counts before and
    // after calling _file_save_upload_from_form() are correct.
    $this->assertText($error);
    $this->assertRaw('Number of error messages before _file_save_upload_from_form(): 1');
    $this->assertRaw('Number of error messages after _file_save_upload_from_form(): 1');

    // Test a successful upload with no messages.
    $edit = [
      'files[file_test_upload][]' => $file_system->realpath($this->image->getFileUri()),
    ];
    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Ensure the error message is not present and the counts before and after
    // calling _file_save_upload_from_form() are correct.
    $this->assertNoText($error);
    $this->assertRaw('Number of error messages before _file_save_upload_from_form(): 0');
    $this->assertRaw('Number of error messages after _file_save_upload_from_form(): 0');
  }

  /**
   * Tests that multiple validation errors are combined in one message.
   */
  public function testCombinedErrorMessages() {
    $textfile = current($this->drupalGetTestFiles('text'));
    $this->assertTrue(is_file($textfile->uri), 'The text file we are going to upload exists.');

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');

    // Can't use drupalPostForm() for set nonexistent fields.
    $this->drupalGet('file-test/save_upload_from_form_test');
    $client = $this->getSession()->getDriver()->getClient();
    $submit_xpath = $this->assertSession()->buttonExists('Submit')->getXpath();
    $form = $client->getCrawler()->filterXPath($submit_xpath)->form();
    $edit = [
      'allow_all_extensions' => FALSE,
      'is_image_file' => TRUE,
      'extensions' => 'jpeg',
    ];
    $edit += $form->getPhpValues();
    $files['files']['file_test_upload'][0] = $file_system->realpath($this->phpfile->uri);
    $files['files']['file_test_upload'][1] = $file_system->realpath($textfile->uri);
    $client->request($form->getMethod(), $form->getUri(), $edit, $files);

    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('Epic upload FAIL!'), 'Found the failure message.');

    // Search for combined error message followed by a formatted list of messages.
    $this->assertRaw(t('One or more files could not be uploaded.') . '<div class="item-list">', 'Error message contains combined list of validation errors.');
  }

  /**
   * Tests highlighting of file upload field when it has an error.
   */
  public function testUploadFieldIsHighlighted() {
    $this->assertEqual(0, count($this->cssSelect('input[name="files[file_test_upload][]"].error')), 'Successful file upload has no error.');

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $edit = [
      'files[file_test_upload][]' => $file_system->realpath($this->image->getFileUri()),
      'extensions' => 'foo',
    ];
    $this->drupalPostForm('file-test/save_upload_from_form_test', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('Epic upload FAIL!'), 'Found the failure message.');
    $this->assertEqual(1, count($this->cssSelect('input[name="files[file_test_upload][]"].error')), 'File upload field has error.');
  }

}
