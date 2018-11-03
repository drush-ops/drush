<?php

namespace Drupal\system\Tests\Routing;

use Drupal\Core\Cache\Cache;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Language\LanguageInterface;
use Drupal\simpletest\WebTestBase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Drupal\Core\Url;

/**
 * Functional class for the full integrated routing system.
 *
 * @group Routing
 */
class RouterTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['router_test'];

  /**
   * Confirms that our FinishResponseSubscriber logic works properly.
   */
  public function testFinishResponseSubscriber() {
    $renderer_required_cache_contexts = ['languages:' . LanguageInterface::TYPE_INTERFACE, 'theme', 'user.permissions'];
    $expected_cache_contexts = Cache::mergeContexts($renderer_required_cache_contexts, ['url.query_args:' . MainContentViewSubscriber::WRAPPER_FORMAT]);

    // Confirm that the router can get to a controller.
    $this->drupalGet('router_test/test1');
    $this->assertRaw('test1', 'The correct string was returned because the route was successful.');
    // Check expected headers from FinishResponseSubscriber.
    $headers = $this->drupalGetHeaders();
    $this->assertEqual($headers['x-ua-compatible'], 'IE=edge');
    $this->assertEqual($headers['content-language'], 'en');
    $this->assertEqual($headers['x-content-type-options'], 'nosniff');
    $this->assertEqual($headers['x-frame-options'], 'SAMEORIGIN');

    $this->drupalGet('router_test/test2');
    $this->assertRaw('test2', 'The correct string was returned because the route was successful.');
    // Check expected headers from FinishResponseSubscriber.
    $headers = $this->drupalGetHeaders();
    $this->assertEqual($headers['x-drupal-cache-contexts'], implode(' ', $expected_cache_contexts));
    $this->assertEqual($headers['x-drupal-cache-tags'], 'config:user.role.anonymous http_response rendered');
    // Confirm that the page wrapping is being added, so we're not getting a
    // raw body returned.
    $this->assertRaw('</html>', 'Page markup was found.');
    // In some instances, the subrequest handling may get confused and render
    // a page inception style.  This test verifies that is not happening.
    $this->assertNoPattern('#</body>.*</body>#s', 'There was no double-page effect from a misrendered subrequest.');

    // Confirm that route-level access check's cacheability is applied to the
    // X-Drupal-Cache-Contexts and X-Drupal-Cache-Tags headers.
    // 1. controller result: render array, globally cacheable route access.
    $this->drupalGet('router_test/test18');
    $headers = $this->drupalGetHeaders();
    $this->assertEqual($headers['x-drupal-cache-contexts'], implode(' ', Cache::mergeContexts($renderer_required_cache_contexts, ['url'])));
    $this->assertEqual($headers['x-drupal-cache-tags'], 'config:user.role.anonymous foo http_response rendered');
    // 2. controller result: render array, per-role cacheable route access.
    $this->drupalGet('router_test/test19');
    $headers = $this->drupalGetHeaders();
    $this->assertEqual($headers['x-drupal-cache-contexts'], implode(' ', Cache::mergeContexts($renderer_required_cache_contexts, ['url', 'user.roles'])));
    $this->assertEqual($headers['x-drupal-cache-tags'], 'config:user.role.anonymous foo http_response rendered');
    // 3. controller result: Response object, globally cacheable route access.
    $this->drupalGet('router_test/test1');
    $headers = $this->drupalGetHeaders();
    $this->assertFalse(isset($headers['x-drupal-cache-contexts']));
    $this->assertFalse(isset($headers['x-drupal-cache-tags']));
    // 4. controller result: Response object, per-role cacheable route access.
    $this->drupalGet('router_test/test20');
    $headers = $this->drupalGetHeaders();
    $this->assertFalse(isset($headers['x-drupal-cache-contexts']));
    $this->assertFalse(isset($headers['x-drupal-cache-tags']));
    // 5. controller result: CacheableResponse object, globally cacheable route access.
    $this->drupalGet('router_test/test21');
    $headers = $this->drupalGetHeaders();
    $this->assertEqual($headers['x-drupal-cache-contexts'], '');
    $this->assertEqual($headers['x-drupal-cache-tags'], 'http_response');
    // 6. controller result: CacheableResponse object, per-role cacheable route access.
    $this->drupalGet('router_test/test22');
    $headers = $this->drupalGetHeaders();
    $this->assertEqual($headers['x-drupal-cache-contexts'], 'user.roles');
    $this->assertEqual($headers['x-drupal-cache-tags'], 'http_response');

    // Finally, verify that the X-Drupal-Cache-Contexts and X-Drupal-Cache-Tags
    // headers are not sent when their container parameter is set to FALSE.
    $this->drupalGet('router_test/test18');
    $headers = $this->drupalGetHeaders();
    $this->assertTrue(isset($headers['x-drupal-cache-contexts']));
    $this->assertTrue(isset($headers['x-drupal-cache-tags']));
    $this->setHttpResponseDebugCacheabilityHeaders(FALSE);
    $this->drupalGet('router_test/test18');
    $headers = $this->drupalGetHeaders();
    $this->assertFalse(isset($headers['x-drupal-cache-contexts']));
    $this->assertFalse(isset($headers['x-drupal-cache-tags']));
  }

  /**
   * Confirms that multiple routes with the same path do not cause an error.
   */
  public function testDuplicateRoutePaths() {
    // Tests two routes with exactly the same path. The route with the maximum
    // fit and lowest sorting route name will match, regardless of the order the
    // routes are declared.
    // @see \Drupal\Core\Routing\RouteProvider::getRoutesByPath()
    $this->drupalGet('router-test/duplicate-path2');
    $this->assertResponse(200);
    $this->assertRaw('router_test.two_duplicate1');

    // Tests three routes with same the path. One of the routes the path has a
    // different case.
    $this->drupalGet('router-test/case-sensitive-duplicate-path3');
    $this->assertResponse(200);
    $this->assertRaw('router_test.case_sensitive_duplicate1');
    // While case-insensitive matching works, exact matches are preferred.
    $this->drupalGet('router-test/case-sensitive-Duplicate-PATH3');
    $this->assertResponse(200);
    $this->assertRaw('router_test.case_sensitive_duplicate2');
    // Test that case-insensitive matching works, falling back to the first
    // route defined.
    $this->drupalGet('router-test/case-sensitive-Duplicate-Path3');
    $this->assertResponse(200);
    $this->assertRaw('router_test.case_sensitive_duplicate1');
  }

  /**
   * Confirms that placeholders in paths work correctly.
   */
  public function testControllerPlaceholders() {
    // Test with 0 and a random value.
    $values = ["0", $this->randomMachineName()];
    foreach ($values as $value) {
      $this->drupalGet('router_test/test3/' . $value);
      $this->assertResponse(200);
      $this->assertRaw($value, 'The correct string was returned because the route was successful.');
    }

    // Confirm that the page wrapping is being added, so we're not getting a
    // raw body returned.
    $this->assertRaw('</html>', 'Page markup was found.');

    // In some instances, the subrequest handling may get confused and render
    // a page inception style.  This test verifies that is not happening.
    $this->assertNoPattern('#</body>.*</body>#s', 'There was no double-page effect from a misrendered subrequest.');
  }

  /**
   * Confirms that default placeholders in paths work correctly.
   */
  public function testControllerPlaceholdersDefaultValues() {
    $this->drupalGet('router_test/test4');
    $this->assertResponse(200);
    $this->assertRaw('narf', 'The correct string was returned because the route was successful.');

    // Confirm that the page wrapping is being added, so we're not getting a
    // raw body returned.
    $this->assertRaw('</html>', 'Page markup was found.');

    // In some instances, the subrequest handling may get confused and render
    // a page inception style.  This test verifies that is not happening.
    $this->assertNoPattern('#</body>.*</body>#s', 'There was no double-page effect from a misrendered subrequest.');
  }

  /**
   * Confirms that default placeholders in paths work correctly.
   */
  public function testControllerPlaceholdersDefaultValuesProvided() {
    $this->drupalGet('router_test/test4/barf');
    $this->assertResponse(200);
    $this->assertRaw('barf', 'The correct string was returned because the route was successful.');

    // Confirm that the page wrapping is being added, so we're not getting a
    // raw body returned.
    $this->assertRaw('</html>', 'Page markup was found.');

    // In some instances, the subrequest handling may get confused and render
    // a page inception style.  This test verifies that is not happening.
    $this->assertNoPattern('#</body>.*</body>#s', 'There was no double-page effect from a misrendered subrequest.');
  }

  /**
   * Checks that dynamically defined and altered routes work correctly.
   *
   * @see \Drupal\router_test\RouteSubscriber
   */
  public function testDynamicRoutes() {
    // Test the altered route.
    $this->drupalGet('router_test/test6');
    $this->assertResponse(200);
    $this->assertRaw('test5', 'The correct string was returned because the route was successful.');
  }

  /**
   * Checks that a request with text/html response gets rendered as a page.
   */
  public function testControllerResolutionPage() {
    $this->drupalGet('/router_test/test10');

    $this->assertRaw('abcde', 'Correct body was found.');

    // Confirm that the page wrapping is being added, so we're not getting a
    // raw body returned.
    $this->assertRaw('</html>', 'Page markup was found.');

    // In some instances, the subrequest handling may get confused and render
    // a page inception style. This test verifies that is not happening.
    $this->assertNoPattern('#</body>.*</body>#s', 'There was no double-page effect from a misrendered subrequest.');
  }

  /**
   * Checks the generate method on the url generator using the front router.
   */
  public function testUrlGeneratorFront() {
    $front_url = Url::fromRoute('<front>', [], ['absolute' => TRUE]);
    // Compare to the site base URL.
    $base_url = Url::fromUri('base:/', ['absolute' => TRUE]);
    $this->assertIdentical($base_url->toString(), $front_url->toString());
  }

  /**
   * Tests that a page trying to match a path will succeed.
   */
  public function testRouterMatching() {
    $this->drupalGet('router_test/test14/1');
    $this->assertResponse(200);
    $this->assertText('User route "entity.user.canonical" was matched.');

    // Try to match a route for a non-existent user.
    $this->drupalGet('router_test/test14/2');
    $this->assertResponse(200);
    $this->assertText('Route not matched.');

    // Check that very long paths don't cause an error.
    $path = 'router_test/test1';
    $suffix = '/d/r/u/p/a/l';
    for ($i = 0; $i < 10; $i++) {
      $path .= $suffix;
      $this->drupalGet($path);
      $this->assertResponse(404);
    }
  }

  /**
   * Tests that a PSR-7 response works.
   */
  public function testRouterResponsePsr7() {
    $this->drupalGet('/router_test/test23');
    $this->assertResponse(200);
    $this->assertText('test23');
  }

  /**
   * Tests the user account on the DIC.
   */
  public function testUserAccount() {
    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);

    $second_account = $this->drupalCreateUser();

    $this->drupalGet('router_test/test12/' . $second_account->id());
    $this->assertText($account->getUsername() . ':' . $second_account->getUsername());
    $this->assertEqual($account->id(), $this->loggedInUser->id(), 'Ensure that the user was not changed.');

    $this->drupalGet('router_test/test13/' . $second_account->id());
    $this->assertText($account->getUsername() . ':' . $second_account->getUsername());
    $this->assertEqual($account->id(), $this->loggedInUser->id(), 'Ensure that the user was not changed.');
  }

  /**
   * Checks that an ajax request gets rendered as an Ajax response, by mime.
   */
  public function testControllerResolutionAjax() {
    // This will fail with a JSON parse error if the request is not routed to
    // The correct controller.
    $this->drupalGetAjax('/router_test/test10');

    $this->assertEqual($this->drupalGetHeader('Content-Type'), 'application/json', 'Correct mime content type was returned');

    $this->assertRaw('abcde', 'Correct body was found.');
  }

  /**
   * Tests that routes no longer exist for a module that has been uninstalled.
   */
  public function testRouterUninstallInstall() {
    \Drupal::service('module_installer')->uninstall(['router_test']);
    \Drupal::service('router.builder')->rebuild();
    try {
      \Drupal::service('router.route_provider')->getRouteByName('router_test.1');
      $this->fail('Route was delete on uninstall.');
    }
    catch (RouteNotFoundException $e) {
      $this->pass('Route was delete on uninstall.');
    }
    // Install the module again.
    \Drupal::service('module_installer')->install(['router_test']);
    \Drupal::service('router.builder')->rebuild();
    $route = \Drupal::service('router.route_provider')->getRouteByName('router_test.1');
    $this->assertNotNull($route, 'Route exists after module installation');
  }

  /**
   * Ensure that multiple leading slashes are redirected.
   */
  public function testLeadingSlashes() {
    $request = $this->container->get('request_stack')->getCurrentRequest();
    $url = $request->getUriForPath('//router_test/test1');
    $this->drupalGet($url);
    $this->assertEqual(1, $this->redirectCount, $url . " redirected to " . $this->url);
    $this->assertUrl($request->getUriForPath('/router_test/test1'));

    // It should not matter how many leading slashes are used and query strings
    // should be preserved.
    $url = $request->getUriForPath('/////////////////////////////////////////////////router_test/test1') . '?qs=test';
    $this->drupalGet($url);
    $this->assertEqual(1, $this->redirectCount, $url . " redirected to " . $this->url);
    $this->assertUrl($request->getUriForPath('/router_test/test1') . '?qs=test');

    // Ensure that external URLs in destination query params are not redirected
    // to.
    $url = $request->getUriForPath('/////////////////////////////////////////////////router_test/test1') . '?qs=test&destination=http://www.example.com%5c@drupal8alt.test';
    $this->drupalGet($url);
    $this->assertEqual(1, $this->redirectCount, $url . " redirected to " . $this->url);
    $this->assertUrl($request->getUriForPath('/router_test/test1') . '?qs=test');
  }

}
