<?php

namespace Drupal\Tests\views\Functional;

use Drupal\Core\EventSubscriber\MainContentViewSubscriber;

/**
 * Tests the ajax view functionality.
 *
 * @group views
 */
class ViewAjaxTest extends ViewTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_ajax_view', 'test_view'];

  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    $this->enableViewsTestModule();
  }

  /**
   * Tests an ajax view.
   */
  public function testAjaxView() {
    $this->drupalGet('test_ajax_view');

    $drupal_settings = $this->getDrupalSettings();
    $this->assertTrue(isset($drupal_settings['views']['ajax_path']), 'The Ajax callback path is set in drupalSettings.');
    $this->assertEqual(count($drupal_settings['views']['ajaxViews']), 1);
    $view_entry = array_keys($drupal_settings['views']['ajaxViews'])[0];
    $this->assertEqual($drupal_settings['views']['ajaxViews'][$view_entry]['view_name'], 'test_ajax_view', 'The view\'s ajaxViews array entry has the correct \'view_name\' key.');
    $this->assertEqual($drupal_settings['views']['ajaxViews'][$view_entry]['view_display_id'], 'page_1', 'The view\'s ajaxViews array entry has the correct \'view_display_id\' key.');
  }

  /**
   * Ensures that non-ajax view cannot be accessed via an ajax HTTP request.
   */
  public function testNonAjaxViewViaAjax() {
    $client = $this->getHttpClient();
    $response = $client->request('POST', $this->buildUrl('views/ajax'), [
      'form_params' => ['view_name' => 'test_ajax_view', 'view_display_id' => 'default'],
      'query' => [MainContentViewSubscriber::WRAPPER_FORMAT => 'drupal_ajax'],
    ]);
    $this->assertEquals(200, $response->getStatusCode());
    $response = $client->request('POST', $this->buildUrl('views/ajax'), [
      'form_params' => ['view_name' => 'test_view', 'view_display_id' => 'default'],
      'query' => [MainContentViewSubscriber::WRAPPER_FORMAT => 'drupal_ajax'],
      'http_errors' => FALSE,
    ]);
    $this->assertEquals(403, $response->getStatusCode());
  }

}
