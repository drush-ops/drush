<?php

namespace Drupal\Tests\rest\Functional;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\rest\RestResourceConfigInterface;
use Drupal\user\Entity\User;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests binary data file upload route.
 */
abstract class FileUploadResourceTestBase extends ResourceTestBase {

  use BcTimestampNormalizerUnixTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['rest_test', 'entity_test', 'file'];

  /**
   * {@inheritdoc}
   */
  protected static $resourceConfigId = 'file.upload';

  /**
   * The POST URI.
   *
   * @var string
   */
  protected static $postUri = 'file/upload/entity_test/entity_test/field_rest_file_test';

  /**
   * Test file data.
   *
   * @var string
   */
  protected $testFileData = 'Hares sit on chairs, and mules sit on stools.';

  /**
   * The test field storage config.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected $fieldStorage;

  /**
   * The field config.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $field;

  /**
   * The parent entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Created file entity.
   *
   * @var \Drupal\file\Entity\File
   */
  protected $file;

  /**
   * An authenticated user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * The entity storage for the 'file' entity type.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileStorage;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->fileStorage = $this->container->get('entity_type.manager')
      ->getStorage('file');

    // Add a file field.
    $this->fieldStorage = FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_rest_file_test',
      'type' => 'file',
      'settings' => [
        'uri_scheme' => 'public',
      ],
    ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $this->fieldStorage->save();

    $this->field = FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_rest_file_test',
      'bundle' => 'entity_test',
      'settings' => [
        'file_directory' => 'foobar',
        'file_extensions' => 'txt',
        'max_filesize' => '',
      ],
    ])
      ->setLabel('Test file field')
      ->setTranslatable(FALSE);
    $this->field->save();

    // Create an entity that a file can be attached to.
    $this->entity = EntityTest::create([
      'name' => 'Llama',
      'type' => 'entity_test',
    ]);
    $this->entity->setOwnerId(isset($this->account) ? $this->account->id() : 0);
    $this->entity->save();

    // Provision entity_test resource.
    $this->resourceConfigStorage->create([
      'id' => 'entity.entity_test',
      'granularity' => RestResourceConfigInterface::RESOURCE_GRANULARITY,
      'configuration' => [
        'methods' => ['POST'],
        'formats' => [static::$format],
        'authentication' => [static::$auth],
      ],
      'status' => TRUE,
    ])->save();

    $this->refreshTestStateAfterRestConfigChange();
  }

  /**
   * Tests using the file upload POST route.
   */
  public function testPostFileUpload() {
    $this->initAuthentication();

    $this->provisionResource([static::$format], static::$auth ? [static::$auth] : [], ['POST']);

    $uri = Url::fromUri('base:' . static::$postUri);

    // DX: 403 when unauthorized.
    $response = $this->fileRequest($uri, $this->testFileData);
    $this->assertResourceErrorResponse(403, $this->getExpectedUnauthorizedAccessMessage('POST'), $response);

    $this->setUpAuthorization('POST');

    // 404 when the field name is invalid.
    $invalid_uri = Url::fromUri('base:file/upload/entity_test/entity_test/field_rest_file_test_invalid');
    $response = $this->fileRequest($invalid_uri, $this->testFileData);
    $this->assertResourceErrorResponse(404, 'Field "field_rest_file_test_invalid" does not exist', $response);

    // This request will have the default 'application/octet-stream' content
    // type header.
    $response = $this->fileRequest($uri, $this->testFileData);
    $this->assertSame(201, $response->getStatusCode());
    $expected = $this->getExpectedNormalizedEntity();
    $this->assertResponseData($expected, $response);

    // Check the actual file data.
    $this->assertSame($this->testFileData, file_get_contents('public://foobar/example.txt'));

    // Test the file again but using 'filename' in the Content-Disposition
    // header with no 'file' prefix.
    $response = $this->fileRequest($uri, $this->testFileData, ['Content-Disposition' => 'filename="example.txt"']);
    $this->assertSame(201, $response->getStatusCode());
    $expected = $this->getExpectedNormalizedEntity(2, 'example_0.txt');
    $this->assertResponseData($expected, $response);

    // Check the actual file data.
    $this->assertSame($this->testFileData, file_get_contents('public://foobar/example_0.txt'));
    $this->assertTrue($this->fileStorage->loadUnchanged(1)->isTemporary());

    // Verify that we can create an entity that references the uploaded file.
    $entity_test_post_url = Url::fromRoute('rest.entity.entity_test.POST')
      ->setOption('query', ['_format' => static::$format]);
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions('POST'));

    $request_options[RequestOptions::BODY] = $this->serializer->encode($this->getNormalizedPostEntity(), static::$format);
    $response = $this->request('POST', $entity_test_post_url, $request_options);
    $this->assertResourceResponse(201, FALSE, $response);
    $this->assertTrue($this->fileStorage->loadUnchanged(1)->isPermanent());
    $this->assertSame([
      [
        'target_id' => '1',
        'display' => NULL,
        'description' => "The most fascinating file ever!",
      ],
    ], EntityTest::load(2)->get('field_rest_file_test')->getValue());
  }

  /**
   * Returns the normalized POST entity referencing the uploaded file.
   *
   * @return array
   *
   * @see ::testPostFileUpload()
   * @see \Drupal\Tests\rest\Functional\EntityResource\EntityTest\EntityTestResourceTestBase::getNormalizedPostEntity()
   */
  protected function getNormalizedPostEntity() {
    return [
      'type' => [
        [
          'value' => 'entity_test',
        ],
      ],
      'name' => [
        [
          'value' => 'Dramallama',
        ],
      ],
      'field_rest_file_test' => [
        [
          'target_id' => 1,
          'description' => 'The most fascinating file ever!',
        ],
      ],
    ];
  }

  /**
   * Tests using the file upload POST route with invalid headers.
   */
  public function testPostFileUploadInvalidHeaders() {
    $this->initAuthentication();

    $this->provisionResource([static::$format], static::$auth ? [static::$auth] : [], ['POST']);

    $this->setUpAuthorization('POST');

    $uri = Url::fromUri('base:' . static::$postUri);

    // The wrong content type header should return a 415 code.
    $response = $this->fileRequest($uri, $this->testFileData, ['Content-Type' => static::$mimeType]);
    $this->assertResourceErrorResponse(415, sprintf('No route found that matches "Content-Type: %s"', static::$mimeType), $response);

    // An empty Content-Disposition header should return a 400.
    $response = $this->fileRequest($uri, $this->testFileData, ['Content-Disposition' => '']);
    $this->assertResourceErrorResponse(400, '"Content-Disposition" header is required. A file name in the format "filename=FILENAME" must be provided', $response);

    // An empty filename with a context in the Content-Disposition header should
    // return a 400.
    $response = $this->fileRequest($uri, $this->testFileData, ['Content-Disposition' => 'file; filename=""']);
    $this->assertResourceErrorResponse(400, 'No filename found in "Content-Disposition" header. A file name in the format "filename=FILENAME" must be provided', $response);

    // An empty filename without a context in the Content-Disposition header
    // should return a 400.
    $response = $this->fileRequest($uri, $this->testFileData, ['Content-Disposition' => 'filename=""']);
    $this->assertResourceErrorResponse(400, 'No filename found in "Content-Disposition" header. A file name in the format "filename=FILENAME" must be provided', $response);

    // An invalid key-value pair in the Content-Disposition header should return
    // a 400.
    $response = $this->fileRequest($uri, $this->testFileData, ['Content-Disposition' => 'not_a_filename="example.txt"']);
    $this->assertResourceErrorResponse(400, 'No filename found in "Content-Disposition" header. A file name in the format "filename=FILENAME" must be provided', $response);

    // Using filename* extended format is not currently supported.
    $response = $this->fileRequest($uri, $this->testFileData, ['Content-Disposition' => 'filename*="UTF-8 \' \' example.txt"']);
    $this->assertResourceErrorResponse(400, 'The extended "filename*" format is currently not supported in the "Content-Disposition" header', $response);
  }

  /**
   * Tests using the file upload POST route with a duplicate file name.
   *
   * A new file should be created with a suffixed name.
   */
  public function testPostFileUploadDuplicateFile() {
    $this->initAuthentication();

    $this->provisionResource([static::$format], static::$auth ? [static::$auth] : [], ['POST']);

    $this->setUpAuthorization('POST');

    $uri = Url::fromUri('base:' . static::$postUri);

    // This request will have the default 'application/octet-stream' content
    // type header.
    $response = $this->fileRequest($uri, $this->testFileData);

    $this->assertSame(201, $response->getStatusCode());

    // Make the same request again. The file should be saved as a new file
    // entity that has the same file name but a suffixed file URI.
    $response = $this->fileRequest($uri, $this->testFileData);
    $this->assertSame(201, $response->getStatusCode());

    // Loading expected normalized data for file 2, the duplicate file.
    $expected = $this->getExpectedNormalizedEntity(2, 'example_0.txt');
    $this->assertResponseData($expected, $response);

    // Check the actual file data.
    $this->assertSame($this->testFileData, file_get_contents('public://foobar/example_0.txt'));
  }

  /**
   * Tests using the file upload route with any path prefixes being stripped.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Disposition#Directives
   */
  public function testFileUploadStrippedFilePath() {
    $this->initAuthentication();

    $this->provisionResource([static::$format], static::$auth ? [static::$auth] : [], ['POST']);

    $this->setUpAuthorization('POST');

    $uri = Url::fromUri('base:' . static::$postUri);

    $response = $this->fileRequest($uri, $this->testFileData, ['Content-Disposition' => 'file; filename="directory/example.txt"']);
    $this->assertSame(201, $response->getStatusCode());
    $expected = $this->getExpectedNormalizedEntity();
    $this->assertResponseData($expected, $response);

    // Check the actual file data. It should have been written to the configured
    // directory, not /foobar/directory/example.txt.
    $this->assertSame($this->testFileData, file_get_contents('public://foobar/example.txt'));

    $response = $this->fileRequest($uri, $this->testFileData, ['Content-Disposition' => 'file; filename="../../example_2.txt"']);
    $this->assertSame(201, $response->getStatusCode());
    $expected = $this->getExpectedNormalizedEntity(2, 'example_2.txt', TRUE);
    $this->assertResponseData($expected, $response);

    // Check the actual file data. It should have been written to the configured
    // directory, not /foobar/directory/example.txt.
    $this->assertSame($this->testFileData, file_get_contents('public://foobar/example_2.txt'));
    $this->assertFalse(file_exists('../../example_2.txt'));

    // Check a path from the root. Extensions have to be empty to allow a file
    // with no extension to pass validation.
    $this->field->setSetting('file_extensions', '')
      ->save();
    $this->refreshTestStateAfterRestConfigChange();

    $response = $this->fileRequest($uri, $this->testFileData, ['Content-Disposition' => 'file; filename="/etc/passwd"']);
    $this->assertSame(201, $response->getStatusCode());
    $expected = $this->getExpectedNormalizedEntity(3, 'passwd', TRUE);
    // This mime will be guessed as there is no extension.
    $expected['filemime'][0]['value'] = 'application/octet-stream';
    $this->assertResponseData($expected, $response);

    // Check the actual file data. It should have been written to the configured
    // directory, not /foobar/directory/example.txt.
    $this->assertSame($this->testFileData, file_get_contents('public://foobar/passwd'));
  }

  /**
   * Tests using the file upload route with a unicode file name.
   */
  public function testFileUploadUnicodeFilename() {
    $this->initAuthentication();

    $this->provisionResource([static::$format], static::$auth ? [static::$auth] : [], ['POST']);

    $this->setUpAuthorization('POST');

    $uri = Url::fromUri('base:' . static::$postUri);

    $response = $this->fileRequest($uri, $this->testFileData, ['Content-Disposition' => 'file; filename="example-✓.txt"']);
    $this->assertSame(201, $response->getStatusCode());
    $expected = $this->getExpectedNormalizedEntity(1, 'example-✓.txt', TRUE);
    $this->assertResponseData($expected, $response);
    $this->assertSame($this->testFileData, file_get_contents('public://foobar/example-✓.txt'));
  }

  /**
   * Tests using the file upload route with a zero byte file.
   */
  public function testFileUploadZeroByteFile() {
    $this->initAuthentication();

    $this->provisionResource([static::$format], static::$auth ? [static::$auth] : [], ['POST']);

    $this->setUpAuthorization('POST');

    $uri = Url::fromUri('base:' . static::$postUri);

    // Test with a zero byte file.
    $response = $this->fileRequest($uri, NULL);
    $this->assertSame(201, $response->getStatusCode());
    $expected = $this->getExpectedNormalizedEntity();
    // Modify the default expected data to account for the 0 byte file.
    $expected['filesize'][0]['value'] = 0;
    $this->assertResponseData($expected, $response);

    // Check the actual file data.
    $this->assertSame('', file_get_contents('public://foobar/example.txt'));
  }

  /**
   * Tests using the file upload route with an invalid file type.
   */
  public function testFileUploadInvalidFileType() {
    $this->initAuthentication();

    $this->provisionResource([static::$format], static::$auth ? [static::$auth] : [], ['POST']);

    $this->setUpAuthorization('POST');

    $uri = Url::fromUri('base:' . static::$postUri);

    // Test with a JSON file.
    $response = $this->fileRequest($uri, '{"test":123}', ['Content-Disposition' => 'filename="example.json"']);
    $this->assertResourceErrorResponse(422, PlainTextOutput::renderFromHtml("Unprocessable Entity: file validation failed.\nOnly files with the following extensions are allowed: <em class=\"placeholder\">txt</em>."), $response);

    // Make sure that no file was saved.
    $this->assertEmpty(File::load(1));
    $this->assertFalse(file_exists('public://foobar/example.txt'));
  }

  /**
   * Tests using the file upload route with a file size larger than allowed.
   */
  public function testFileUploadLargerFileSize() {
    // Set a limit of 50 bytes.
    $this->field->setSetting('max_filesize', 50)
      ->save();
    $this->refreshTestStateAfterRestConfigChange();

    $this->initAuthentication();

    $this->provisionResource([static::$format], static::$auth ? [static::$auth] : [], ['POST']);

    $this->setUpAuthorization('POST');

    $uri = Url::fromUri('base:' . static::$postUri);

    // Generate a string larger than the 50 byte limit set.
    $response = $this->fileRequest($uri, $this->randomString(100));
    $this->assertResourceErrorResponse(422, PlainTextOutput::renderFromHtml("Unprocessable Entity: file validation failed.\nThe file is <em class=\"placeholder\">100 bytes</em> exceeding the maximum file size of <em class=\"placeholder\">50 bytes</em>."), $response);

    // Make sure that no file was saved.
    $this->assertEmpty(File::load(1));
    $this->assertFalse(file_exists('public://foobar/example.txt'));
  }

  /**
   * Tests using the file upload POST route with malicious extensions.
   */
  public function testFileUploadMaliciousExtension() {
    $this->initAuthentication();

    $this->provisionResource([static::$format], static::$auth ? [static::$auth] : [], ['POST']);
    // Allow all file uploads but system.file::allow_insecure_uploads is set to
    // FALSE.
    $this->field->setSetting('file_extensions', '')->save();
    $this->refreshTestStateAfterRestConfigChange();

    $this->setUpAuthorization('POST');

    $uri = Url::fromUri('base:' . static::$postUri);

    $php_string = '<?php print "Drupal"; ?>';

    // Test using a masked exploit file.
    $response = $this->fileRequest($uri, $php_string, ['Content-Disposition' => 'filename="example.php"']);
    // The filename is not munged because .txt is added and it is a known
    // extension to apache.
    $expected = $this->getExpectedNormalizedEntity(1, 'example.php.txt', TRUE);
    // Override the expected filesize.
    $expected['filesize'][0]['value'] = strlen($php_string);
    $this->assertResponseData($expected, $response);
    $this->assertTrue(file_exists('public://foobar/example.php.txt'));

    // Add php as an allowed format. Allow insecure uploads still being FALSE
    // should still not allow this. So it should still have a .txt extension
    // appended even though it is not in the list of allowed extensions.
    $this->field->setSetting('file_extensions', 'php')
      ->save();
    $this->refreshTestStateAfterRestConfigChange();

    $response = $this->fileRequest($uri, $php_string, ['Content-Disposition' => 'filename="example_2.php"']);
    $expected = $this->getExpectedNormalizedEntity(2, 'example_2.php.txt', TRUE);
    // Override the expected filesize.
    $expected['filesize'][0]['value'] = strlen($php_string);
    $this->assertResponseData($expected, $response);
    $this->assertTrue(file_exists('public://foobar/example_2.php.txt'));
    $this->assertFalse(file_exists('public://foobar/example_2.php'));

    // Allow .doc file uploads and ensure even a mis-configured apache will not
    // fallback to php because the filename will be munged.
    $this->field->setSetting('file_extensions', 'doc')->save();
    $this->refreshTestStateAfterRestConfigChange();

    // Test using a masked exploit file.
    $response = $this->fileRequest($uri, $php_string, ['Content-Disposition' => 'filename="example_3.php.doc"']);
    // The filename is munged.
    $expected = $this->getExpectedNormalizedEntity(3, 'example_3.php_.doc', TRUE);
    // Override the expected filesize.
    $expected['filesize'][0]['value'] = strlen($php_string);
    // The file mime should be 'application/msword'.
    $expected['filemime'][0]['value'] = 'application/msword';
    $this->assertResponseData($expected, $response);
    $this->assertTrue(file_exists('public://foobar/example_3.php_.doc'));
    $this->assertFalse(file_exists('public://foobar/example_3.php.doc'));

    // Now allow insecure uploads.
    \Drupal::configFactory()
      ->getEditable('system.file')
      ->set('allow_insecure_uploads', TRUE)
      ->save();
    // Allow all file uploads. This is very insecure.
    $this->field->setSetting('file_extensions', '')->save();
    $this->refreshTestStateAfterRestConfigChange();

    $response = $this->fileRequest($uri, $php_string, ['Content-Disposition' => 'filename="example_4.php"']);
    $expected = $this->getExpectedNormalizedEntity(4, 'example_4.php', TRUE);
    // Override the expected filesize.
    $expected['filesize'][0]['value'] = strlen($php_string);
    // The file mime should also now be PHP.
    $expected['filemime'][0]['value'] = 'application/x-httpd-php';
    $this->assertResponseData($expected, $response);
    $this->assertTrue(file_exists('public://foobar/example_4.php'));
  }

  /**
   * Tests using the file upload POST route no extension configured.
   */
  public function testFileUploadNoExtensionSetting() {
    $this->initAuthentication();

    $this->provisionResource([static::$format], static::$auth ? [static::$auth] : [], ['POST']);

    $this->setUpAuthorization('POST');

    $uri = Url::fromUri('base:' . static::$postUri);

    $this->field->setSetting('file_extensions', '')
      ->save();
    $this->refreshTestStateAfterRestConfigChange();

    $response = $this->fileRequest($uri, $this->testFileData, ['Content-Disposition' => 'filename="example.txt"']);
    $expected = $this->getExpectedNormalizedEntity(1, 'example.txt', TRUE);

    $this->assertResponseData($expected, $response);
    $this->assertTrue(file_exists('public://foobar/example.txt'));
  }

  /**
   * {@inheritdoc}
   */
  protected function assertNormalizationEdgeCases($method, Url $url, array $request_options) {
    // The file upload resource only accepts binary data, so there are no
    // normalization edge cases to test, as there are no normalized entity
    // representations incoming.
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    return "The following permissions are required: 'administer entity_test content' OR 'administer entity_test_with_bundle content' OR 'create entity_test entity_test_with_bundle entities'.";
  }

  /**
   * Gets the expected file entity.
   *
   * @param int $fid
   *   The file ID to load and create normalized data for.
   * @param string $expected_filename
   *   The expected filename for the stored file.
   * @param bool $expected_as_filename
   *   Whether the expected filename should be the filename property too.
   *
   * @return array
   *   The expected normalized data array.
   */
  protected function getExpectedNormalizedEntity($fid = 1, $expected_filename = 'example.txt', $expected_as_filename = FALSE) {
    $author = User::load(static::$auth ? $this->account->id() : 0);
    $file = File::load($fid);

    $expected_normalization = [
      'fid' => [
        [
          'value' => (int) $file->id(),
        ],
      ],
      'uuid' => [
        [
          'value' => $file->uuid(),
        ],
      ],
      'langcode' => [
        [
          'value' => 'en',
        ],
      ],
      'uid' => [
        [
          'target_id' => (int) $author->id(),
          'target_type' => 'user',
          'target_uuid' => $author->uuid(),
          'url' => base_path() . 'user/' . $author->id(),
        ],
      ],
      'filename' => [
        [
          'value' => $expected_as_filename ? $expected_filename : 'example.txt',
        ],
      ],
      'uri' => [
        [
          'value' => 'public://foobar/' . $expected_filename,
          'url' => base_path() . $this->siteDirectory . '/files/foobar/' . rawurlencode($expected_filename),
        ],
      ],
      'filemime' => [
        [
          'value' => 'text/plain',
        ],
      ],
      'filesize' => [
        [
          'value' => strlen($this->testFileData),
        ],
      ],
      'status' => [
        [
          'value' => FALSE,
        ],
      ],
      'created' => [
        $this->formatExpectedTimestampItemValues($file->getCreatedTime()),
      ],
      'changed' => [
        $this->formatExpectedTimestampItemValues($file->getChangedTime()),
      ],
    ];

    return $expected_normalization;
  }

  /**
   * Performs a file upload request. Wraps the Guzzle HTTP client.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   *
   * @param \Drupal\Core\Url $url
   *   URL to request.
   * @param string $file_contents
   *   The file contents to send as the request body.
   * @param array $headers
   *   Additional headers to send with the request. Defaults will be added for
   *   Content-Type and Content-Disposition.
   *
   * @return \Psr\Http\Message\ResponseInterface
   */
  protected function fileRequest(Url $url, $file_contents, array $headers = []) {
    // Set the format for the response.
    $url->setOption('query', ['_format' => static::$format]);

    $request_options = [];
    $request_options[RequestOptions::HEADERS] = $headers + [
      // Set the required (and only accepted) content type for the request.
      'Content-Type' => 'application/octet-stream',
      // Set the required Content-Disposition header for the file name.
      'Content-Disposition' => 'file; filename="example.txt"',
    ];
    $request_options[RequestOptions::BODY] = $file_contents;
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions('POST'));

    return $this->request('POST', $url, $request_options);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
        $this->grantPermissionsToTestedRole(['view test entity']);
        break;
      case 'POST':
        $this->grantPermissionsToTestedRole(['create entity_test entity_test_with_bundle entities', 'access content']);
        break;
    }
  }

  /**
   * Asserts expected normalized data matches response data.
   *
   * @param array $expected
   *   The expected data.
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The file upload response.
   */
  protected function assertResponseData(array $expected, ResponseInterface $response) {
    static::recursiveKSort($expected);
    $actual = $this->serializer->decode((string) $response->getBody(), static::$format);
    static::recursiveKSort($actual);

    $this->assertSame($expected, $actual);
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessCacheability() {
    // There is cacheability metadata to check as file uploads only allows POST
    // requests, which will not return cacheable responses.
  }

}
