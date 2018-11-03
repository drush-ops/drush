<?php

namespace Drupal\views_ui\Tests;

use Drupal\views\Tests\ViewTestBase;

/**
 * Provides a base class for testing the Views UI.
 *
 * @deprecated in Drupal 8.4.0 and will be removed before Drupal 9.0.x.
 *   Use \Drupal\Tests\views_ui\Functional\UITestBase.
 */
abstract class UITestBase extends ViewTestBase {

  /**
   * An admin user with the 'administer views' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * An admin user with administrative permissions for views, blocks, and nodes.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $fullAdminUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'views_ui', 'block', 'taxonomy'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    $this->enableViewsTestModule();

    $this->adminUser = $this->drupalCreateUser(['administer views']);

    $this->fullAdminUser = $this->drupalCreateUser(['administer views',
      'administer blocks',
      'bypass node access',
      'access user profiles',
      'view all revisions',
      'administer permissions',
    ]);
    $this->drupalLogin($this->fullAdminUser);

    @trigger_error('\Drupal\views_ui\Tests\UITestBase is deprecated in Drupal 8.4.0 and will be removed before Drupal 9.0.x. Instead, use \Drupal\Tests\views_ui\Functional\UITestBase', E_USER_DEPRECATED);
  }

  /**
   * A helper method which creates a random view.
   */
  public function randomView(array $view = []) {
    // Create a new view in the UI.
    $default = [];
    $default['label'] = $this->randomMachineName(16);
    $default['id'] = strtolower($this->randomMachineName(16));
    $default['description'] = $this->randomMachineName(16);
    $default['page[create]'] = TRUE;
    $default['page[path]'] = $default['id'];

    $view += $default;

    $this->drupalPostForm('admin/structure/views/add', $view, t('Save and edit'));

    return $default;
  }

  /**
   * {@inheritdoc}
   */
  protected function drupalGet($path, array $options = [], array $headers = []) {
    $url = $this->buildUrl($path, $options);

    // Ensure that each nojs page is accessible via ajax as well.
    if (strpos($url, 'nojs') !== FALSE) {
      $url = str_replace('nojs', 'ajax', $url);
      $result = $this->drupalGet($url, $options, $headers);
      $this->assertResponse(200);
      $this->assertHeader('Content-Type', 'application/json');
      $this->assertTrue(json_decode($result), 'Ensure that the AJAX request returned valid content.');
    }

    return parent::drupalGet($path, $options, $headers);
  }

}
