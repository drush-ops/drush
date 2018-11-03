<?php

namespace Drupal\Tests\user\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;

/**
 * Ensure that login works as expected.
 *
 * @group user
 */
class UserLoginTest extends BrowserTestBase {

  /**
   * Tests login with destination.
   */
  public function testLoginCacheTagsAndDestination() {
    $this->drupalGet('user/login');
    // The user login form says "Enter your <site name> username.", hence it
    // depends on config:system.site, and its cache tags should be present.
    $this->assertCacheTag('config:system.site');

    $user = $this->drupalCreateUser([]);
    $this->drupalGet('user/login', ['query' => ['destination' => 'foo']]);
    $edit = ['name' => $user->getUserName(), 'pass' => $user->passRaw];
    $this->drupalPostForm(NULL, $edit, t('Log in'));
    $this->assertUrl('foo', [], 'Redirected to the correct URL');
  }

  /**
   * Test the global login flood control.
   */
  public function testGlobalLoginFloodControl() {
    $this->config('user.flood')
      ->set('ip_limit', 10)
      // Set a high per-user limit out so that it is not relevant in the test.
      ->set('user_limit', 4000)
      ->save();

    $user1 = $this->drupalCreateUser([]);
    $incorrect_user1 = clone $user1;
    $incorrect_user1->passRaw .= 'incorrect';

    // Try 2 failed logins.
    for ($i = 0; $i < 2; $i++) {
      $this->assertFailedLogin($incorrect_user1);
    }

    // A successful login will not reset the IP-based flood control count.
    $this->drupalLogin($user1);
    $this->drupalLogout();

    // Try 8 more failed logins, they should not trigger the flood control
    // mechanism.
    for ($i = 0; $i < 8; $i++) {
      $this->assertFailedLogin($incorrect_user1);
    }

    // The next login trial should result in an IP-based flood error message.
    $this->assertFailedLogin($incorrect_user1, 'ip');

    // A login with the correct password should also result in a flood error
    // message.
    $this->assertFailedLogin($user1, 'ip');
  }

  /**
   * Test the per-user login flood control.
   */
  public function testPerUserLoginFloodControl() {
    $this->config('user.flood')
      // Set a high global limit out so that it is not relevant in the test.
      ->set('ip_limit', 4000)
      ->set('user_limit', 3)
      ->save();

    $user1 = $this->drupalCreateUser([]);
    $incorrect_user1 = clone $user1;
    $incorrect_user1->passRaw .= 'incorrect';

    $user2 = $this->drupalCreateUser([]);

    // Try 2 failed logins.
    for ($i = 0; $i < 2; $i++) {
      $this->assertFailedLogin($incorrect_user1);
    }

    // A successful login will reset the per-user flood control count.
    $this->drupalLogin($user1);
    $this->drupalLogout();

    // Try 3 failed logins for user 1, they will not trigger flood control.
    for ($i = 0; $i < 3; $i++) {
      $this->assertFailedLogin($incorrect_user1);
    }

    // Try one successful attempt for user 2, it should not trigger any
    // flood control.
    $this->drupalLogin($user2);
    $this->drupalLogout();

    // Try one more attempt for user 1, it should be rejected, even if the
    // correct password has been used.
    $this->assertFailedLogin($user1, 'user');
  }

  /**
   * Test that user password is re-hashed upon login after changing $count_log2.
   */
  public function testPasswordRehashOnLogin() {
    // Determine default log2 for phpass hashing algorithm
    $default_count_log2 = 16;

    // Retrieve instance of password hashing algorithm
    $password_hasher = $this->container->get('password');

    // Create a new user and authenticate.
    $account = $this->drupalCreateUser([]);
    $password = $account->passRaw;
    $this->drupalLogin($account);
    $this->drupalLogout();
    // Load the stored user. The password hash should reflect $default_count_log2.
    $user_storage = $this->container->get('entity.manager')->getStorage('user');
    $account = User::load($account->id());
    $this->assertIdentical($password_hasher->getCountLog2($account->getPassword()), $default_count_log2);

    // Change the required number of iterations by loading a test-module
    // containing the necessary container builder code and then verify that the
    // users password gets rehashed during the login.
    $overridden_count_log2 = 19;
    \Drupal::service('module_installer')->install(['user_custom_phpass_params_test']);
    $this->resetAll();

    $account->passRaw = $password;
    $this->drupalLogin($account);
    // Load the stored user, which should have a different password hash now.
    $user_storage->resetCache([$account->id()]);
    $account = $user_storage->load($account->id());
    $this->assertIdentical($password_hasher->getCountLog2($account->getPassword()), $overridden_count_log2);
    $this->assertTrue($password_hasher->check($password, $account->getPassword()));
  }

  /**
   * Make an unsuccessful login attempt.
   *
   * @param \Drupal\user\Entity\User $account
   *   A user object with name and passRaw attributes for the login attempt.
   * @param mixed $flood_trigger
   *   (optional) Whether or not to expect that the flood control mechanism
   *    will be triggered. Defaults to NULL.
   *   - Set to 'user' to expect a 'too many failed logins error.
   *   - Set to any value to expect an error for too many failed logins per IP
   *   .
   *   - Set to NULL to expect a failed login.
   */
  public function assertFailedLogin($account, $flood_trigger = NULL) {
    $edit = [
      'name' => $account->getUsername(),
      'pass' => $account->passRaw,
    ];
    $this->drupalPostForm('user/login', $edit, t('Log in'));
    $this->assertNoFieldByXPath("//input[@name='pass' and @value!='']", NULL, 'Password value attribute is blank.');
    if (isset($flood_trigger)) {
      if ($flood_trigger == 'user') {
        $this->assertRaw(\Drupal::translation()->formatPlural($this->config('user.flood')->get('user_limit'), 'There has been more than one failed login attempt for this account. It is temporarily blocked. Try again later or <a href=":url">request a new password</a>.', 'There have been more than @count failed login attempts for this account. It is temporarily blocked. Try again later or <a href=":url">request a new password</a>.', [':url' => \Drupal::url('user.pass')]));
      }
      else {
        // No uid, so the limit is IP-based.
        $this->assertRaw(t('Too many failed login attempts from your IP address. This IP address is temporarily blocked. Try again later or <a href=":url">request a new password</a>.', [':url' => \Drupal::url('user.pass')]));
      }
    }
    else {
      $this->assertText(t('Unrecognized username or password. Forgot your password?'));
    }
  }

}
