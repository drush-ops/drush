<?php

namespace Drupal\Tests\user\Functional\Views;

/**
 * A common test base class for the user access plugin tests.
 */
abstract class AccessTestBase extends UserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['block'];

  /**
   * Contains a user object that has no special permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * Contains a user object that has the 'views_test_data test permission'.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $normalUser;

  /**
   * Contains a role ID that is used by the webUser.
   *
   * @var string
   */
  protected $webRole;

  /**
   * Contains a role ID that is used by the normalUser.
   *
   * @var string
   */
  protected $normalRole;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);
    $this->drupalPlaceBlock('system_breadcrumb_block');

    $this->enableViewsTestModule();

    $this->webUser = $this->drupalCreateUser();
    $roles = $this->webUser->getRoles();
    $this->webRole = $roles[0];

    $this->normalRole = $this->drupalCreateRole([]);
    $this->normalUser = $this->drupalCreateUser(['views_test_data test permission']);
    $this->normalUser->addRole($this->normalRole);
    $this->normalUser->save();
    // @todo when all the plugin information is cached make a reset function and
    // call it here.
  }

}
