<?php

namespace Drupal\Tests\image\Functional;

use Drupal\image\Entity\ImageStyle;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests the functions for generating paths and URLs for image styles.
 *
 * @group image
 */
class ImageStylesPathAndUrlTest extends BrowserTestBase {

  use TestFileCreationTrait {
    getTestFiles as drupalGetTestFiles;
    compareFiles as drupalCompareFiles;
  }

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['image', 'image_module_test', 'language'];

  /**
   * The image style.
   *
   * @var \Drupal\image\ImageStyleInterface
   */
  protected $style;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->style = ImageStyle::create([
      'name' => 'style_foo',
      'label' => $this->randomString(),
    ]);
    $this->style->save();

    // Create a new language.
    ConfigurableLanguage::createFromLangcode('fr')->save();
  }

  /**
   * Tests \Drupal\image\ImageStyleInterface::buildUri().
   */
  public function testImageStylePath() {
    $scheme = 'public';
    $actual = $this->style->buildUri("$scheme://foo/bar.gif");
    $expected = "$scheme://styles/" . $this->style->id() . "/$scheme/foo/bar.gif";
    $this->assertEqual($actual, $expected, 'Got the path for a file URI.');

    $actual = $this->style->buildUri('foo/bar.gif');
    $expected = "$scheme://styles/" . $this->style->id() . "/$scheme/foo/bar.gif";
    $this->assertEqual($actual, $expected, 'Got the path for a relative file path.');
  }

  /**
   * Tests an image style URL using the "public://" scheme.
   */
  public function testImageStyleUrlAndPathPublic() {
    $this->doImageStyleUrlAndPathTests('public');
  }

  /**
   * Tests an image style URL using the "private://" scheme.
   */
  public function testImageStyleUrlAndPathPrivate() {
    $this->doImageStyleUrlAndPathTests('private');
  }

  /**
   * Tests an image style URL with the "public://" scheme and unclean URLs.
   */
  public function testImageStyleUrlAndPathPublicUnclean() {
    $this->doImageStyleUrlAndPathTests('public', FALSE);
  }

  /**
   * Tests an image style URL with the "private://" schema and unclean URLs.
   */
  public function testImageStyleUrlAndPathPrivateUnclean() {
    $this->doImageStyleUrlAndPathTests('private', FALSE);
  }

  /**
   * Tests an image style URL with the "public://" schema and language prefix.
   */
  public function testImageStyleUrlAndPathPublicLanguage() {
    $this->doImageStyleUrlAndPathTests('public', TRUE, TRUE, 'fr');
  }

  /**
   * Tests an image style URL with the "private://" schema and language prefix.
   */
  public function testImageStyleUrlAndPathPrivateLanguage() {
    $this->doImageStyleUrlAndPathTests('private', TRUE, TRUE, 'fr');
  }

  /**
   * Tests an image style URL with a file URL that has an extra slash in it.
   */
  public function testImageStyleUrlExtraSlash() {
    $this->doImageStyleUrlAndPathTests('public', TRUE, TRUE);
  }

  /**
   * Tests that an invalid source image returns a 404.
   */
  public function testImageStyleUrlForMissingSourceImage() {
    $non_existent_uri = 'public://foo.png';
    $generated_url = $this->style->buildUrl($non_existent_uri);
    $this->drupalGet($generated_url);
    $this->assertResponse(404, 'Accessing an image style URL with a source image that does not exist provides a 404 error response.');
  }

  /**
   * Tests building an image style URL.
   */
  public function doImageStyleUrlAndPathTests($scheme, $clean_url = TRUE, $extra_slash = FALSE, $langcode = FALSE) {
    $this->prepareRequestForGenerator($clean_url);

    // Make the default scheme neither "public" nor "private" to verify the
    // functions work for other than the default scheme.
    $this->config('system.file')->set('default_scheme', 'temporary')->save();

    // Create the directories for the styles.
    $directory = $scheme . '://styles/' . $this->style->id();
    $status = file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
    $this->assertNotIdentical(FALSE, $status, 'Created the directory for the generated images for the test style.');

    // Override the language to build the URL for the correct language.
    if ($langcode) {
      $language_manager = \Drupal::service('language_manager');
      $language = $language_manager->getLanguage($langcode);
      $language_manager->setConfigOverrideLanguage($language);
    }

    // Create a working copy of the file.
    $files = $this->drupalGetTestFiles('image');
    $file = array_shift($files);
    $original_uri = file_unmanaged_copy($file->uri, $scheme . '://', FILE_EXISTS_RENAME);
    // Let the image_module_test module know about this file, so it can claim
    // ownership in hook_file_download().
    \Drupal::state()->set('image.test_file_download', $original_uri);
    $this->assertNotIdentical(FALSE, $original_uri, 'Created the generated image file.');

    // Get the URL of a file that has not been generated and try to create it.
    $generated_uri = $this->style->buildUri($original_uri);
    $this->assertFalse(file_exists($generated_uri), 'Generated file does not exist.');
    $generate_url = $this->style->buildUrl($original_uri, $clean_url);

    // Make sure that language prefix is never added to the image style URL.
    if ($langcode) {
      $this->assertTrue(strpos($generate_url, "/$langcode/") === FALSE, 'Langcode was not found in the image style URL.');
    }

    // Ensure that the tests still pass when the file is generated by accessing
    // a poorly constructed (but still valid) file URL that has an extra slash
    // in it.
    if ($extra_slash) {
      $modified_uri = str_replace('://', ':///', $original_uri);
      $this->assertNotEqual($original_uri, $modified_uri, 'An extra slash was added to the generated file URI.');
      $generate_url = $this->style->buildUrl($modified_uri, $clean_url);
    }
    if (!$clean_url) {
      $this->assertTrue(strpos($generate_url, 'index.php/') !== FALSE, 'When using non-clean URLS, the system path contains the script name.');
    }
    // Add some extra chars to the token.
    $this->drupalGet(str_replace(IMAGE_DERIVATIVE_TOKEN . '=', IMAGE_DERIVATIVE_TOKEN . '=Zo', $generate_url));
    $this->assertResponse(404, 'Image was inaccessible at the URL with an invalid token.');
    // Change the parameter name so the token is missing.
    $this->drupalGet(str_replace(IMAGE_DERIVATIVE_TOKEN . '=', 'wrongparam=', $generate_url));
    $this->assertResponse(404, 'Image was inaccessible at the URL with a missing token.');

    // Check that the generated URL is the same when we pass in a relative path
    // rather than a URI. We need to temporarily switch the default scheme to
    // match the desired scheme before testing this, then switch it back to the
    // "temporary" scheme used throughout this test afterwards.
    $this->config('system.file')->set('default_scheme', $scheme)->save();
    $relative_path = file_uri_target($original_uri);
    $generate_url_from_relative_path = $this->style->buildUrl($relative_path, $clean_url);
    $this->assertEqual($generate_url, $generate_url_from_relative_path);
    $this->config('system.file')->set('default_scheme', 'temporary')->save();

    // Fetch the URL that generates the file.
    $this->drupalGet($generate_url);
    $this->assertResponse(200, 'Image was generated at the URL.');
    $this->assertTrue(file_exists($generated_uri), 'Generated file does exist after we accessed it.');
    // assertRaw can't be used with string containing non UTF-8 chars.
    $this->assertNotEmpty(file_get_contents($generated_uri), 'URL returns expected file.');
    $image = $this->container->get('image.factory')->get($generated_uri);
    $this->assertEqual($this->drupalGetHeader('Content-Type'), $image->getMimeType(), 'Expected Content-Type was reported.');
    $this->assertEqual($this->drupalGetHeader('Content-Length'), $image->getFileSize(), 'Expected Content-Length was reported.');

    // Check that we did not download the original file.
    $original_image = $this->container->get('image.factory')
      ->get($original_uri);
    $this->assertNotEqual($this->drupalGetHeader('Content-Length'), $original_image->getFileSize());

    if ($scheme == 'private') {
      $this->assertEqual($this->drupalGetHeader('Expires'), 'Sun, 19 Nov 1978 05:00:00 GMT', 'Expires header was sent.');
      $this->assertNotEqual(strpos($this->drupalGetHeader('Cache-Control'), 'no-cache'), FALSE, 'Cache-Control header contains \'no-cache\' to prevent caching.');
      $this->assertEqual($this->drupalGetHeader('X-Image-Owned-By'), 'image_module_test', 'Expected custom header has been added.');

      // Make sure that a second request to the already existing derivative
      // works too.
      $this->drupalGet($generate_url);
      $this->assertResponse(200, 'Image was generated at the URL.');

      // Check that the second request also returned the generated image.
      $this->assertEqual($this->drupalGetHeader('Content-Length'), $image->getFileSize());

      // Check that we did not download the original file.
      $this->assertNotEqual($this->drupalGetHeader('Content-Length'), $original_image->getFileSize());

      // Make sure that access is denied for existing style files if we do not
      // have access.
      \Drupal::state()->delete('image.test_file_download');
      $this->drupalGet($generate_url);
      $this->assertResponse(403, 'Confirmed that access is denied for the private image style.');

      // Repeat this with a different file that we do not have access to and
      // make sure that access is denied.
      $file_noaccess = array_shift($files);
      $original_uri_noaccess = file_unmanaged_copy($file_noaccess->uri, $scheme . '://', FILE_EXISTS_RENAME);
      $generated_uri_noaccess = $scheme . '://styles/' . $this->style->id() . '/' . $scheme . '/' . drupal_basename($original_uri_noaccess);
      $this->assertFalse(file_exists($generated_uri_noaccess), 'Generated file does not exist.');
      $generate_url_noaccess = $this->style->buildUrl($original_uri_noaccess);

      $this->drupalGet($generate_url_noaccess);
      $this->assertResponse(403, 'Confirmed that access is denied for the private image style.');
      // Verify that images are not appended to the response.
      // Currently this test only uses PNG images.
      if (strpos($generate_url, '.png') === FALSE) {
        $this->fail('Confirming that private image styles are not appended require PNG file.');
      }
      else {
        // Check for PNG-Signature
        // (cf. http://www.libpng.org/pub/png/book/chapter08.html#png.ch08.div.2)
        // in the response body.
        $raw = $this->getSession()->getPage()->getContent();
        $this->assertFalse(strpos($raw, chr(137) . chr(80) . chr(78) . chr(71) . chr(13) . chr(10) . chr(26) . chr(10)));
      }
    }
    else {
      $this->assertEqual($this->drupalGetHeader('Expires'), 'Sun, 19 Nov 1978 05:00:00 GMT', 'Expires header was sent.');
      $this->assertEqual(strpos($this->drupalGetHeader('Cache-Control'), 'no-cache'), FALSE, 'Cache-Control header contains \'no-cache\' to prevent caching.');

      if ($clean_url) {
        // Add some extra chars to the token.
        $this->drupalGet(str_replace(IMAGE_DERIVATIVE_TOKEN . '=', IMAGE_DERIVATIVE_TOKEN . '=Zo', $generate_url));
        $this->assertResponse(200, 'Existing image was accessible at the URL with an invalid token.');
      }
    }

    // Allow insecure image derivatives to be created for the remainder of this
    // test.
    $this->config('image.settings')
      ->set('allow_insecure_derivatives', TRUE)
      ->save();

    // Create another working copy of the file.
    $files = $this->drupalGetTestFiles('image');
    $file = array_shift($files);
    $original_uri = file_unmanaged_copy($file->uri, $scheme . '://', FILE_EXISTS_RENAME);
    // Let the image_module_test module know about this file, so it can claim
    // ownership in hook_file_download().
    \Drupal::state()->set('image.test_file_download', $original_uri);

    // Suppress the security token in the URL, then get the URL of a file that
    // has not been created and try to create it. Check that the security token
    // is not present in the URL but that the image is still accessible.
    $this->config('image.settings')->set('suppress_itok_output', TRUE)->save();
    $generated_uri = $this->style->buildUri($original_uri);
    $this->assertFalse(file_exists($generated_uri), 'Generated file does not exist.');
    $generate_url = $this->style->buildUrl($original_uri, $clean_url);
    $this->assertIdentical(strpos($generate_url, IMAGE_DERIVATIVE_TOKEN . '='), FALSE, 'The security token does not appear in the image style URL.');
    $this->drupalGet($generate_url);
    $this->assertResponse(200, 'Image was accessible at the URL with a missing token.');

    // Stop supressing the security token in the URL.
    $this->config('image.settings')->set('suppress_itok_output', FALSE)->save();
    // Ensure allow_insecure_derivatives is enabled.
    $this->assertEqual($this->config('image.settings')
      ->get('allow_insecure_derivatives'), TRUE);
    // Check that a security token is still required when generating a second
    // image derivative using the first one as a source.
    $nested_url = $this->style->buildUrl($generated_uri, $clean_url);
    $matches_expected_url_format = (boolean) preg_match('/styles\/' . $this->style->id() . '\/' . $scheme . '\/styles\/' . $this->style->id() . '\/' . $scheme . '/', $nested_url);
    $this->assertTrue($matches_expected_url_format, "URL for a derivative of an image style matches expected format.");
    $nested_url_with_wrong_token = str_replace(IMAGE_DERIVATIVE_TOKEN . '=', 'wrongparam=', $nested_url);
    $this->drupalGet($nested_url_with_wrong_token);
    $this->assertResponse(404, 'Image generated from an earlier derivative was inaccessible at the URL with a missing token.');
    // Check that this restriction cannot be bypassed by adding extra slashes
    // to the URL.
    $this->drupalGet(substr_replace($nested_url_with_wrong_token, '//styles/', strrpos($nested_url_with_wrong_token, '/styles/'), strlen('/styles/')));
    $this->assertResponse(404, 'Image generated from an earlier derivative was inaccessible at the URL with a missing token, even with an extra forward slash in the URL.');
    $this->drupalGet(substr_replace($nested_url_with_wrong_token, '////styles/', strrpos($nested_url_with_wrong_token, '/styles/'), strlen('/styles/')));
    $this->assertResponse(404, 'Image generated from an earlier derivative was inaccessible at the URL with a missing token, even with multiple forward slashes in the URL.');
    // Make sure the image can still be generated if a correct token is used.
    $this->drupalGet($nested_url);
    $this->assertResponse(200, 'Image was accessible when a correct token was provided in the URL.');

    // Check that requesting a nonexistent image does not create any new
    // directories in the file system.
    $directory = $scheme . '://styles/' . $this->style->id() . '/' . $scheme . '/' . $this->randomMachineName();
    $this->drupalGet(file_create_url($directory . '/' . $this->randomString()));
    $this->assertFalse(file_exists($directory), 'New directory was not created in the filesystem when requesting an unauthorized image.');
  }

}
