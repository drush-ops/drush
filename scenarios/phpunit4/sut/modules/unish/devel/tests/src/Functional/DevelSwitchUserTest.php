<?php

namespace Drupal\Tests\devel\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests switch user.
 *
 * @group devel
 */
class DevelSwitchUserTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['devel', 'block'];

  /**
   * The block used by this test.
   *
   * @var \Drupal\block\BlockInterface
   */
  protected $block;

  /**
   * The devel user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $develUser;

  /**
   * The switch user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $switchUser;

  /**
   * The web user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $webUser;

  /**
   * Set up test.
   */
  protected function setUp() {
    parent::setUp();

    $this->block = $this->drupalPlaceBlock('devel_switch_user', ['id' => 'switch-user']);

    $this->develUser = $this->drupalCreateUser(['access devel information', 'switch users']);
    $this->switchUser = $this->drupalCreateUser(['switch users']);
    $this->webUser = $this->drupalCreateUser();
  }

  /**
   * Tests switch user.
   */
  public function testSwitchUser() {
    $this->drupalLogin($this->webUser);

    $this->drupalGet('');
    $this->assertNoText($this->block->label(), 'Block title was not found.');

    // Ensure that a token is required to switch user.
    $this->drupalGet('/devel/switch/' . $this->webUser->getDisplayName());
    $this->assertResponse(403);

    $this->drupalLogin($this->develUser);

    $this->drupalGet('');
    $this->assertText($this->block->label(), 'Block title was found.');

    // Ensure that if name in not passed the controller returns access denied.
    $this->drupalGet('/devel/switch');
    $this->assertResponse(403);

    // Ensure that a token is required to switch user.
    $this->drupalGet('/devel/switch/' . $this->switchUser->getDisplayName());
    $this->assertResponse(403);

    // Switch to another user account.
    $this->drupalGet('/user/' . $this->switchUser->id());
    $this->clickLink($this->switchUser->getDisplayName());
    $this->assertSessionByUid($this->switchUser->id());
    $this->assertNoSessionByUid($this->develUser->id());

    // Switch back to initial account.
    $this->clickLink($this->develUser->getDisplayName());
    $this->assertNoSessionByUid($this->switchUser->id());
    $this->assertSessionByUid($this->develUser->id());

    // Use the search form to switch to another account.
    $edit = ['userid' => $this->switchUser->getDisplayName()];
    $this->drupalPostForm(NULL, $edit, t('Switch'));
    $this->assertSessionByUid($this->switchUser->id());
    $this->assertNoSessionByUid($this->develUser->id());
  }

  /**
   * Tests the switch user block configuration.
   */
  public function testSwitchUserBlockConfiguration() {
    $anonymous = \Drupal::config('user.settings')->get('anonymous');

    // Create some users for the test.
    for ($i = 0; $i < 12; $i++) {
      $this->drupalCreateUser();
    }

    $this->drupalLogin($this->develUser);

    $this->drupalGet('');
    $this->assertText($this->block->label(), 'Block title was found.');

    // Ensure that block default configuration is effectively used. The block
    // default configuration is the following:
    // - list_size : 12
    // - include_anon : FALSE
    // - show_form : TRUE
    $this->assertSwitchUserSearchForm();
    $this->assertSwitchUserListCount(12);
    $this->assertSwitchUserListNoContainsUser($anonymous);

    // Ensure that changing the list_size configuration property the number of
    // user displayed in the list change.
    $this->setBlockConfiguration('list_size', 4);
    $this->drupalGet('');
    $this->assertSwitchUserListCount(4);

    // Ensure that changing the include_anon configuration property the
    // anonymous user is displayed in the list.
    $this->setBlockConfiguration('include_anon', TRUE);
    $this->drupalGet('');
    $this->assertSwitchUserListContainsUser($anonymous);

    // Ensure that changing the show_form configuration property the
    // form is not displayed.
    $this->setBlockConfiguration('show_form', FALSE);
    $this->drupalGet('');
    $this->assertSwitchUserNoSearchForm();
  }

  /**
   * Test the user list items.
   */
  public function testSwitchUserListItems() {
    $anonymous = \Drupal::config('user.settings')->get('anonymous');

    $this->setBlockConfiguration('list_size', 2);

    // Login as web user so we are sure that this account is prioritized
    // in the list if not enougth user with 'switch users' permission are
    // present.
    $this->drupalLogin($this->webUser);

    $this->drupalLogin($this->develUser);
    $this->drupalGet('');

    // Ensure that user with 'switch users' permission are prioritized.
    $this->assertSwitchUserListCount(2);
    $this->assertSwitchUserListContainsUser($this->develUser->getDisplayName());
    $this->assertSwitchUserListContainsUser($this->switchUser->getDisplayName());

    // Ensure that blocked users are not shown in the list.
    $this->switchUser->set('status', 0)->save();
    $this->drupalGet('');
    $this->assertSwitchUserListCount(2);
    $this->assertSwitchUserListContainsUser($this->develUser->getDisplayName());
    $this->assertSwitchUserListContainsUser($this->webUser->getDisplayName());
    $this->assertSwitchUserListNoContainsUser($this->switchUser->getDisplayName());

    // Ensure that anonymous user are prioritized if include_anon is set to true.
    $this->setBlockConfiguration('include_anon', TRUE);
    $this->drupalGet('');
    $this->assertSwitchUserListCount(2);
    $this->assertSwitchUserListContainsUser($this->develUser->getDisplayName());
    $this->assertSwitchUserListContainsUser($anonymous);

    // Ensure that the switch user block works properly even if no prioritized
    // users are found (special handling for user 1).
    $this->drupalLogout();
    $this->develUser->delete();

    $this->drupalLogin($this->rootUser);
    $this->drupalGet('');
    $this->assertSwitchUserListCount(2);
    $this->assertSwitchUserListContainsUser($this->rootUser->getDisplayName());
    $this->assertSwitchUserListContainsUser($anonymous);

    // Ensure that the switch user block works properly even if no roles have
    // the 'switch users' permission associated (special handling for user 1).
    $roles = user_roles(TRUE, 'switch users');
    \Drupal::entityTypeManager()->getStorage('user_role')->delete($roles);

    $this->drupalGet('');
    $this->assertSwitchUserListCount(2);
    $this->assertSwitchUserListContainsUser($this->rootUser->getDisplayName());
    $this->assertSwitchUserListContainsUser($anonymous);
  }

  /**
   * Helper function for verify the number of items shown in the user list.
   *
   * @param int $number
   *   The expected numer of items.
   */
  public function assertSwitchUserListCount($number) {
    $result = $this->xpath('//div[@id=:block]//ul/li/a', [':block' => 'block-switch-user']);
    $this->assert(count($result) == $number, 'The number of users shown in switch user is correct.');
  }

  /**
   * Helper function for verify if the user list contains a username.
   *
   * @param string $username
   *   The username to check.
   */
  public function assertSwitchUserListContainsUser($username) {
    $result = $this->xpath('//div[@id=:block]//ul/li/a[normalize-space()=:user]', [':block' => 'block-switch-user', ':user' => $username]);
    $this->assert(count($result) > 0, new FormattableMarkup('User "%user" is included in the switch user list.', ['%user' => $username]));
  }

  /**
   * Helper function for verify if the user list not contains a username.
   *
   * @param string $username
   *   The username to check.
   */
  public function assertSwitchUserListNoContainsUser($username) {
    $result = $this->xpath('//div[@id=:block]//ul/li/a[normalize-space()=:user]', [':block' => 'block-switch-user', ':user' => $username]);
    $this->assert(count($result) == 0, new FormattableMarkup('User "%user" is not included in the switch user list.', ['%user' => $username]));
  }

  /**
   * Helper function for verify if the search form is shown.
   */
  public function assertSwitchUserSearchForm() {
    $result = $this->xpath('//div[@id=:block]//form[contains(@class, :form)]', [':block' => 'block-switch-user', ':form' => 'devel-switchuser-form']);
    $this->assert(count($result) > 0, 'The search form is shown.');
  }

  /**
   * Helper function for verify if the search form is not shown.
   */
  public function assertSwitchUserNoSearchForm() {
    $result = $this->xpath('//div[@id=:block]//form[contains(@class, :form)]', [':block' => 'block-switch-user', ':form' => 'devel-switchuser-form']);
    $this->assert(count($result) == 0, 'The search form is not shown.');
  }

  /**
   * Protected helper method to set the test block's configuration.
   */
  protected function setBlockConfiguration($key, $value) {
    $block = $this->block->getPlugin();
    $block->setConfigurationValue($key, $value);
    $this->block->save();
  }

  /**
   * Asserts that there is a session for a given user ID.
   *
   * Based off masquarade module.
   *
   * @param int $uid
   *   The user ID for which to find a session record.
   *
   * TODO find a cleaner way to do this check.
   */
  protected function assertSessionByUid($uid) {
    $query = \Drupal::database()->select('sessions');
    $query->fields('sessions', ['uid']);
    $query->condition('uid', $uid);
    $result = $query->execute()->fetchAll();

    if (empty($result)) {
      $this->fail(new FormattableMarkup('No session found for uid @uid', ['@uid' => $uid]));
    }
    elseif (count($result) > 1) {
      // If there is more than one session, then that must be unexpected.
      $this->fail("Found more than 1 session for uid $uid.");
    }
    else {
      $this->pass("Found session for uid $uid.");
    }
  }

  /**
   * Asserts that no session exists for a given uid.
   *
   * Based off masquarade module.
   *
   * @param int $uid
   *   The user ID to assert.
   *
   * TODO find a cleaner way to do this check.
   */
  protected function assertNoSessionByUid($uid) {
    $query = \Drupal::database()->select('sessions');
    $query->fields('sessions', ['uid']);
    $query->condition('uid', $uid);
    $result = $query->execute()->fetchAll();
    $this->assert(empty($result), "No session for uid $uid found.");
  }

}
