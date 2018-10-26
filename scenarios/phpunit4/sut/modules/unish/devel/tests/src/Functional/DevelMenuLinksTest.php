<?php

namespace Drupal\Tests\devel\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests devel menu links.
 *
 * @group devel
 */
class DevelMenuLinksTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['devel', 'block', 'devel_test'];

  /**
   * The user for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $develUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Devel links currently appears only in the devel menu.
    // Place the devel menu block so we can ensure that these link works
    // properly.
    $this->drupalPlaceBlock('system_menu_block:devel');
    $this->drupalPlaceBlock('page_title_block');

    $this->develUser = $this->drupalCreateUser(['access devel information', 'administer site configuration']);
    $this->drupalLogin($this->develUser);
  }

  /**
   * Tests CSFR protected links.
   */
  public function testCsrfProtectedLinks() {
    // Ensure CSRF link are not accessible directly.
    $this->drupalGet('devel/run-cron');
    $this->assertResponse(403);
    $this->drupalGet('devel/cache/clear');
    $this->assertResponse(403);

    // Ensure clear cache link works properly.
    $this->assertLink('Cache clear');
    $this->clickLink('Cache clear');
    $this->assertText('Cache cleared.');

    // Ensure run cron link works properly.
    $this->assertLink('Run cron');
    $this->clickLink('Run cron');
    $this->assertText('Cron ran successfully.');

    // Ensure CSRF protected links work properly after change session.
    $this->drupalLogout();
    $this->drupalLogin($this->develUser);

    $this->assertLink('Cache clear');
    $this->clickLink('Cache clear');
    $this->assertText('Cache cleared.');

    $this->assertLink('Run cron');
    $this->clickLink('Run cron');
    $this->assertText('Cron ran successfully.');
  }

  /**
   * Tests redirect destination links.
   */
  public function testRedirectDestinationLinks() {
    // By default, in the testing profile, front page is the user canonical URI.
    // For better testing do not use the default frontpage.
    $url = Url::fromRoute('devel.simple_page');
    $destination = Url::fromRoute('devel.simple_page', [], ['absolute' => FALSE]);

    $this->drupalGet($url);
    $this->assertLink(t('Reinstall Modules'));
    $this->clickLink(t('Reinstall Modules'));
    $this->assertUrl('devel/reinstall', ['query' => ['destination' => $destination->toString()]]);

    $this->drupalGet($url);
    $this->assertLink(t('Rebuild Menu'));
    $this->clickLink(t('Rebuild Menu'));
    $this->assertUrl('devel/menu/reset', ['query' => ['destination' => $destination->toString()]]);

    $this->drupalGet($url);
    $this->assertLink(t('Cache clear'));
    $this->clickLink(t('Cache clear'));
    $this->assertText('Cache cleared.');
    $this->assertUrl($url);

    $this->drupalGet($url);
    $this->assertLink(t('Run cron'));
    $this->clickLink(t('Run cron'));
    $this->assertText(t('Cron ran successfully.'));
    $this->assertUrl($url);
  }

}
