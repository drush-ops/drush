<?php

namespace Drupal\Tests\media\FunctionalJavascript;

use Drupal\Core\Session\AccountInterface;
use Drupal\media\Entity\Media;
use Drupal\media_test_oembed\Controller\ResourceController;
use Drupal\Tests\media\Traits\OEmbedTestTrait;
use Drupal\user\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the oembed:video media source.
 *
 * @group media
 */
class MediaSourceOEmbedVideoTest extends MediaSourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['media_test_oembed'];

  use OEmbedTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->lockHttpClientToFixtures();
  }

  /**
   * {@inheritdoc}
   */
  protected function initConfig(ContainerInterface $container) {
    parent::initConfig($container);

    // Enable twig debugging to make testing template usage easy.
    $parameters = $container->getParameter('twig.config');
    $parameters['debug'] = TRUE;
    $this->setContainerParameter('twig.config', $parameters);
  }

  /**
   * Tests the oembed media source.
   */
  public function testMediaOEmbedVideoSource() {
    $media_type_id = 'test_media_oembed_type';
    $provided_fields = [
      'type',
      'title',
      'default_name',
      'author_name',
      'author_url',
      'provider_name',
      'provider_url',
      'cache_age',
      'thumbnail_uri',
      'thumbnail_width',
      'thumbnail_height',
      'url',
      'width',
      'height',
      'html',
    ];

    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    $this->doTestCreateMediaType($media_type_id, 'oembed:video', $provided_fields);

    // Create custom fields for the media type to store metadata attributes.
    $fields = [
      'field_string_width' => 'string',
      'field_string_height' => 'string',
      'field_string_author_name' => 'string',
    ];
    $this->createMediaTypeFields($fields, $media_type_id);

    // Hide the name field widget to test default name generation.
    $this->hideMediaTypeFieldWidget('name', $media_type_id);

    $this->drupalGet("admin/structure/media/manage/$media_type_id");
    // Only accept Vimeo videos.
    $page->checkField("source_configuration[providers][Vimeo]");
    $assert_session->selectExists('field_map[width]')->setValue('field_string_width');
    $assert_session->selectExists('field_map[height]')->setValue('field_string_height');
    $assert_session->selectExists('field_map[author_name]')->setValue('field_string_author_name');
    $assert_session->buttonExists('Save')->press();

    $this->hijackProviderEndpoints();
    $video_url = 'https://vimeo.com/7073899';
    ResourceController::setResourceUrl($video_url, $this->getFixturesDirectory() . '/video_vimeo.json');

    // Create a media item.
    $this->drupalGet("media/add/$media_type_id");
    $assert_session->fieldExists('Remote video URL')->setValue($video_url);
    $assert_session->buttonExists('Save')->press();

    $assert_session->addressEquals('admin/content/media');

    // Get the media entity view URL from the creation message.
    $this->drupalGet($this->assertLinkToCreatedMedia());

    /** @var \Drupal\media\MediaInterface $media */
    $media = Media::load(1);

    // The thumbnail should have been downloaded.
    $thumbnail = $media->getSource()->getMetadata($media, 'thumbnail_uri');
    $this->assertFileExists($thumbnail);

    // Ensure the iframe exists and that its src attribute contains a coherent
    // URL with the query parameters we expect.
    $iframe_url = $assert_session->elementExists('css', 'iframe')->getAttribute('src');
    $iframe_url = parse_url($iframe_url);
    $this->assertStringEndsWith('/media/oembed', $iframe_url['path']);
    $this->assertNotEmpty($iframe_url['query']);
    $query = [];
    parse_str($iframe_url['query'], $query);
    $this->assertSame($video_url, $query['url']);
    $this->assertNotEmpty($query['hash']);

    // Make sure the thumbnail is displayed from uploaded image.
    $assert_session->elementAttributeContains('css', '.image-style-thumbnail', 'src', '/oembed_thumbnails/' . basename($thumbnail));

    // Load the media and check that all fields are properly populated.
    $media = Media::load(1);
    $this->assertSame('Drupal Rap Video - Schipulcon09', $media->getName());
    $this->assertSame('480', $media->field_string_width->value);
    $this->assertSame('360', $media->field_string_height->value);

    // Try to create a media asset from a disallowed provider.
    $this->drupalGet("media/add/$media_type_id");
    $assert_session->fieldExists('Remote video URL')->setValue('http://www.collegehumor.com/video/40003213/grant-and-katie-are-starting-their-own-company');
    $page->pressButton('Save');

    $assert_session->pageTextContains('The CollegeHumor provider is not allowed.');

    // Test anonymous access to media via iframe.
    $this->drupalLogout();

    // Without a hash should be denied.
    $no_hash_query = array_diff_key($query, ['hash' => '']);
    $this->drupalGet('media/oembed', ['query' => $no_hash_query]);
    $assert_session->pageTextNotContains('By the power of Greyskull, Vimeo works!');
    $assert_session->pageTextContains('Access denied');

    // A correct query should be allowed because the anonymous role has the
    // 'view media' permission.
    $this->drupalGet('media/oembed', ['query' => $query]);
    $assert_session->pageTextContains('By the power of Greyskull, Vimeo works!');
    $this->assertRaw('core/themes/stable/templates/content/media-oembed-iframe.html.twig');
    $this->assertNoRaw('core/modules/media/templates/media-oembed-iframe.html.twig');

    // Test themes not inheriting from stable.
    \Drupal::service('theme_handler')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    $this->drupalGet('media/oembed', ['query' => $query]);
    $assert_session->pageTextContains('By the power of Greyskull, Vimeo works!');
    $this->assertNoRaw('core/themes/stable/templates/content/media-oembed-iframe.html.twig');
    $this->assertRaw('core/modules/media/templates/media-oembed-iframe.html.twig');

    // Remove the 'view media' permission to test that this restricts access.
    $role = Role::load(AccountInterface::ANONYMOUS_ROLE);
    $role->revokePermission('view media');
    $role->save();
    $this->drupalGet('media/oembed', ['query' => $query]);
    $assert_session->pageTextNotContains('By the power of Greyskull, Vimeo works!');
    $assert_session->pageTextContains('Access denied');
  }

  /**
   * Test that a security warning appears if iFrame domain is not set.
   */
  public function testOEmbedSecurityWarning() {
    $media_type_id = 'test_media_oembed_type';
    $source_id = 'oembed:video';

    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/structure/media/add');
    $page->fillField('label', $media_type_id);
    $this->getSession()
      ->wait(5000, "jQuery('.machine-name-value').text() === '{$media_type_id}'");

    // Make sure the source is available.
    $assert_session->fieldExists('Media source');
    $assert_session->optionExists('Media source', $source_id);
    $page->selectFieldOption('Media source', $source_id);
    $result = $assert_session->waitForElementVisible('css', 'fieldset[data-drupal-selector="edit-source-configuration"]');
    $this->assertNotEmpty($result);

    $assert_session->pageTextContains('It is potentially insecure to display oEmbed content in a frame');

    $this->config('media.settings')->set('iframe_domain', 'http://example.com')->save();

    $this->drupalGet('admin/structure/media/add');
    $page->fillField('label', $media_type_id);
    $this->getSession()
      ->wait(5000, "jQuery('.machine-name-value').text() === '{$media_type_id}'");

    // Make sure the source is available.
    $assert_session->fieldExists('Media source');
    $assert_session->optionExists('Media source', $source_id);
    $page->selectFieldOption('Media source', $source_id);
    $result = $assert_session->waitForElementVisible('css', 'fieldset[data-drupal-selector="edit-source-configuration"]');
    $this->assertNotEmpty($result);

    $assert_session->pageTextNotContains('It is potentially insecure to display oEmbed content in a frame');
  }

}
