<?php

/**
 * @file
 * Contains \Drupal\Tests\Core\Access\AccessResultTest.
 */

namespace Drupal\Tests\Core\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Access\AccessResult
 * @group Access
 */
class AccessResultTest extends UnitTestCase {

  /**
   * The cache contexts manager.
   *
   * @var \Drupal\Core\Cache\Context\CacheContextsManager|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $cacheContextsManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->cacheContextsManager = $this->getMockBuilder('Drupal\Core\Cache\Context\CacheContextsManager')
      ->disableOriginalConstructor()
      ->getMock();

    $this->cacheContextsManager->method('assertValidTokens')->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $this->cacheContextsManager);
    \Drupal::setContainer($container);
  }

  protected function assertDefaultCacheability(AccessResult $access) {
    $this->assertSame([], $access->getCacheContexts());
    $this->assertSame([], $access->getCacheTags());
    $this->assertSame(Cache::PERMANENT, $access->getCacheMaxAge());
  }

  /**
   * Tests the construction of an AccessResult object.
   *
   * @covers ::neutral
   */
  public function testConstruction() {
    $verify = function (AccessResult $access) {
      $this->assertFalse($access->isAllowed());
      $this->assertFalse($access->isForbidden());
      $this->assertTrue($access->isNeutral());
      $this->assertDefaultCacheability($access);
    };

    // Verify the object when using the constructor.
    $a = new AccessResultNeutral();
    $verify($a);

    // Verify the object when using the ::create() convenience method.
    $b = AccessResult::neutral();
    $verify($b);

    $this->assertEquals($a, $b);
  }

  /**
   * @covers ::allowed
   * @covers ::isAllowed
   * @covers ::isForbidden
   * @covers ::isNeutral
   */
  public function testAccessAllowed() {
    $verify = function (AccessResult $access) {
      $this->assertTrue($access->isAllowed());
      $this->assertFalse($access->isForbidden());
      $this->assertFalse($access->isNeutral());
      $this->assertDefaultCacheability($access);
    };

    // Verify the object when using the ::allowed() convenience static method.
    $b = AccessResult::allowed();
    $verify($b);
  }

  /**
   * @covers ::forbidden
   * @covers ::isAllowed
   * @covers ::isForbidden
   * @covers ::isNeutral
   */
  public function testAccessForbidden() {
    $verify = function (AccessResult $access) {
      $this->assertFalse($access->isAllowed());
      $this->assertTrue($access->isForbidden());
      $this->assertFalse($access->isNeutral());
      $this->assertDefaultCacheability($access);
    };

    // Verify the object when using the ::forbidden() convenience static method.
    $b = AccessResult::forbidden();
    $verify($b);
  }

  /**
   * @covers ::forbidden
   */
  public function testAccessForbiddenReason() {
    $verify = function (AccessResult $access, $reason) {
      $this->assertInstanceOf(AccessResultReasonInterface::class, $access);
      $this->assertSame($reason, $access->getReason());
    };

    $b = AccessResult::forbidden();
    $verify($b, NULL);

    $reason = $this->getRandomGenerator()->string();
    $b = AccessResult::forbidden($reason);
    $verify($b, $reason);

    $b = AccessResult::forbiddenIf(TRUE, $reason);
    $verify($b, $reason);
  }

  /**
   * @covers ::allowedIf
   * @covers ::isAllowed
   * @covers ::isForbidden
   * @covers ::isNeutral
   */
  public function testAccessConditionallyAllowed() {
    $verify = function (AccessResult $access, $allowed) {
      $this->assertSame($allowed, $access->isAllowed());
      $this->assertFalse($access->isForbidden());
      $this->assertSame(!$allowed, $access->isNeutral());
      $this->assertDefaultCacheability($access);
    };

    $b1 = AccessResult::allowedIf(TRUE);
    $verify($b1, TRUE);
    $b2 = AccessResult::allowedIf(FALSE);
    $verify($b2, FALSE);
  }

  /**
   * @covers ::forbiddenIf
   * @covers ::isAllowed
   * @covers ::isForbidden
   * @covers ::isNeutral
   */
  public function testAccessConditionallyForbidden() {
    $verify = function (AccessResult $access, $forbidden) {
      $this->assertFalse($access->isAllowed());
      $this->assertSame($forbidden, $access->isForbidden());
      $this->assertSame(!$forbidden, $access->isNeutral());
      $this->assertDefaultCacheability($access);
    };

    $b1 = AccessResult::forbiddenIf(TRUE);
    $verify($b1, TRUE);
    $b2 = AccessResult::forbiddenIf(FALSE);
    $verify($b2, FALSE);
  }

  /**
   * @covers ::andIf
   */
  public function testAndIf() {
    $neutral = AccessResult::neutral('neutral message');
    $allowed = AccessResult::allowed();
    $forbidden = AccessResult::forbidden('forbidden message');
    $unused_access_result_due_to_lazy_evaluation = $this->getMock('\Drupal\Core\Access\AccessResultInterface');
    $unused_access_result_due_to_lazy_evaluation->expects($this->never())
      ->method($this->anything());

    // ALLOWED && ALLOWED === ALLOWED.
    $access = $allowed->andIf($allowed);
    $this->assertTrue($access->isAllowed());
    $this->assertFalse($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertDefaultCacheability($access);

    // ALLOWED && NEUTRAL === NEUTRAL.
    $access = $allowed->andIf($neutral);
    $this->assertFalse($access->isAllowed());
    $this->assertFalse($access->isForbidden());
    $this->assertTrue($access->isNeutral());
    $this->assertEquals('neutral message', $access->getReason());
    $this->assertDefaultCacheability($access);

    // ALLOWED && FORBIDDEN === FORBIDDEN.
    $access = $allowed->andIf($forbidden);
    $this->assertFalse($access->isAllowed());
    $this->assertTrue($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertEquals('forbidden message', $access->getReason());
    $this->assertDefaultCacheability($access);

    // NEUTRAL && ALLOW == NEUTRAL
    $access = $neutral->andIf($allowed);
    $this->assertFalse($access->isAllowed());
    $this->assertFalse($access->isForbidden());
    $this->assertTrue($access->isNeutral());
    $this->assertEquals('neutral message', $access->getReason());
    $this->assertDefaultCacheability($access);

    // NEUTRAL && NEUTRAL === NEUTRAL.
    $access = $neutral->andIf($neutral);
    $this->assertFalse($access->isAllowed());
    $this->assertFalse($access->isForbidden());
    $this->assertTrue($access->isNeutral());
    $this->assertEquals('neutral message', $access->getReason());
    $this->assertDefaultCacheability($access);

    // NEUTRAL && FORBIDDEN === FORBIDDEN.
    $access = $neutral->andIf($forbidden);
    $this->assertFalse($access->isAllowed());
    $this->assertTrue($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertEquals('forbidden message', $access->getReason());
    $this->assertDefaultCacheability($access);

    // FORBIDDEN && ALLOWED = FORBIDDEN
    $access = $forbidden->andif($allowed);
    $this->assertFalse($access->isAllowed());
    $this->assertTrue($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertEquals('forbidden message', $access->getReason());
    $this->assertDefaultCacheability($access);

    // FORBIDDEN && NEUTRAL = FORBIDDEN
    $access = $forbidden->andif($neutral);
    $this->assertFalse($access->isAllowed());
    $this->assertTrue($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertEquals('forbidden message', $access->getReason());
    $this->assertDefaultCacheability($access);

    // FORBIDDEN && FORBIDDEN = FORBIDDEN
    $access = $forbidden->andif($forbidden);
    $this->assertFalse($access->isAllowed());
    $this->assertTrue($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertEquals('forbidden message', $access->getReason());
    $this->assertDefaultCacheability($access);

    // FORBIDDEN && * === FORBIDDEN: lazy evaluation verification.
    $access = $forbidden->andIf($unused_access_result_due_to_lazy_evaluation);
    $this->assertFalse($access->isAllowed());
    $this->assertTrue($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertEquals('forbidden message', $access->getReason());
    $this->assertDefaultCacheability($access);
  }

  /**
   * @covers ::orIf
   */
  public function testOrIf() {
    $neutral = AccessResult::neutral('neutral message');
    $neutral_other = AccessResult::neutral('other neutral message');
    $neutral_reasonless = AccessResult::neutral();
    $allowed = AccessResult::allowed();
    $forbidden = AccessResult::forbidden('forbidden message');
    $forbidden_other = AccessResult::forbidden('other forbidden message');
    $forbidden_reasonless = AccessResult::forbidden();
    $unused_access_result_due_to_lazy_evaluation = $this->getMock('\Drupal\Core\Access\AccessResultInterface');
    $unused_access_result_due_to_lazy_evaluation->expects($this->never())
      ->method($this->anything());

    // ALLOWED || ALLOWED === ALLOWED.
    $access = $allowed->orIf($allowed);
    $this->assertTrue($access->isAllowed());
    $this->assertFalse($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertDefaultCacheability($access);

    // ALLOWED || NEUTRAL === ALLOWED.
    $access = $allowed->orIf($neutral);
    $this->assertTrue($access->isAllowed());
    $this->assertFalse($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertDefaultCacheability($access);

    // ALLOWED || FORBIDDEN === FORBIDDEN.
    $access = $allowed->orIf($forbidden);
    $this->assertFalse($access->isAllowed());
    $this->assertTrue($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertEquals('forbidden message', $access->getReason());
    $this->assertDefaultCacheability($access);

    // NEUTRAL || NEUTRAL === NEUTRAL.
    $access = $neutral->orIf($neutral);
    $this->assertFalse($access->isAllowed());
    $this->assertFalse($access->isForbidden());
    $this->assertTrue($access->isNeutral());
    $this->assertEquals('neutral message', $access->getReason());
    $this->assertDefaultCacheability($access);
    // Reason inheritance edge case: first reason is kept.
    $access = $neutral->orIf($neutral_other);
    $this->assertEquals('neutral message', $access->getReason());
    $access = $neutral_other->orIf($neutral);
    $this->assertEquals('other neutral message', $access->getReason());
    // Reason inheritance edge case: one of the operands is reasonless.
    $access = $neutral->orIf($neutral_reasonless);
    $this->assertEquals('neutral message', $access->getReason());
    $access = $neutral_reasonless->orIf($neutral);
    $this->assertEquals('neutral message', $access->getReason());
    $access = $neutral_reasonless->orIf($neutral_reasonless);
    $this->assertNull($access->getReason());

    // NEUTRAL || ALLOWED === ALLOWED.
    $access = $neutral->orIf($allowed);
    $this->assertTrue($access->isAllowed());
    $this->assertFalse($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertDefaultCacheability($access);

    // NEUTRAL || FORBIDDEN === FORBIDDEN.
    $access = $neutral->orIf($forbidden);
    $this->assertFalse($access->isAllowed());
    $this->assertTrue($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertEquals('forbidden message', $access->getReason());
    $this->assertDefaultCacheability($access);

    // FORBIDDEN || ALLOWED === FORBIDDEN.
    $access = $forbidden->orIf($allowed);
    $this->assertFalse($access->isAllowed());
    $this->assertTrue($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertEquals('forbidden message', $access->getReason());
    $this->assertDefaultCacheability($access);

    // FORBIDDEN || NEUTRAL === FORBIDDEN.
    $access = $forbidden->orIf($neutral);
    $this->assertFalse($access->isAllowed());
    $this->assertTrue($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertEquals('forbidden message', $access->getReason());
    $this->assertDefaultCacheability($access);

    // FORBIDDEN || FORBIDDEN === FORBIDDEN.
    $access = $forbidden->orIf($forbidden);
    $this->assertFalse($access->isAllowed());
    $this->assertTrue($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertEquals('forbidden message', $access->getReason());
    $this->assertDefaultCacheability($access);
    // Reason inheritance edge case: first reason is kept.
    $access = $forbidden->orIf($forbidden_other);
    $this->assertEquals('forbidden message', $access->getReason());
    $access = $forbidden_other->orIf($forbidden);
    $this->assertEquals('other forbidden message', $access->getReason());
    // Reason inheritance edge case: one of the operands is reasonless.
    $access = $forbidden->orIf($forbidden_reasonless);
    $this->assertEquals('forbidden message', $access->getReason());
    $access = $forbidden_reasonless->orIf($forbidden);
    $this->assertEquals('forbidden message', $access->getReason());
    $access = $forbidden_reasonless->orIf($forbidden_reasonless);
    $this->assertNull($access->getReason());

    // FORBIDDEN || * === FORBIDDEN.
    $access = $forbidden->orIf($unused_access_result_due_to_lazy_evaluation);
    $this->assertFalse($access->isAllowed());
    $this->assertTrue($access->isForbidden());
    $this->assertFalse($access->isNeutral());
    $this->assertEquals('forbidden message', $access->getReason());
    $this->assertDefaultCacheability($access);
  }

  /**
   * @covers ::setCacheMaxAge
   * @covers ::getCacheMaxAge
   */
  public function testCacheMaxAge() {
    $this->assertSame(Cache::PERMANENT, AccessResult::neutral()->getCacheMaxAge());
    $this->assertSame(1337, AccessResult::neutral()->setCacheMaxAge(1337)->getCacheMaxAge());
  }

  /**
   * @covers ::addCacheContexts
   * @covers ::resetCacheContexts
   * @covers ::getCacheContexts
   * @covers ::cachePerPermissions
   * @covers ::cachePerUser
   * @covers ::allowedIfHasPermission
   */
  public function testCacheContexts() {
    $verify = function (AccessResult $access, array $contexts) {
      $this->assertFalse($access->isAllowed());
      $this->assertFalse($access->isForbidden());
      $this->assertTrue($access->isNeutral());
      $this->assertSame(Cache::PERMANENT, $access->getCacheMaxAge());
      $this->assertSame($contexts, $access->getCacheContexts());
      $this->assertSame([], $access->getCacheTags());
    };

    $access = AccessResult::neutral()->addCacheContexts(['foo']);
    $verify($access, ['foo']);
    // Verify resetting works.
    $access->resetCacheContexts();
    $verify($access, []);
    // Verify idempotency.
    $access->addCacheContexts(['foo'])
      ->addCacheContexts(['foo']);
    $verify($access, ['foo']);
    // Verify same values in different call order yields the same result.
    $access->resetCacheContexts()
      ->addCacheContexts(['foo'])
      ->addCacheContexts(['bar']);
    $verify($access, ['bar', 'foo']);
    $access->resetCacheContexts()
      ->addCacheContexts(['bar'])
      ->addCacheContexts(['foo']);
    $verify($access, ['bar', 'foo']);

    // ::cachePerPermissions() convenience method.
    $contexts = ['user.permissions'];
    $a = AccessResult::neutral()->addCacheContexts($contexts);
    $verify($a, $contexts);
    $b = AccessResult::neutral()->cachePerPermissions();
    $verify($b, $contexts);
    $this->assertEquals($a, $b);

    // ::cachePerUser() convenience method.
    $contexts = ['user'];
    $a = AccessResult::neutral()->addCacheContexts($contexts);
    $verify($a, $contexts);
    $b = AccessResult::neutral()->cachePerUser();
    $verify($b, $contexts);
    $this->assertEquals($a, $b);

    // Both.
    $contexts = ['user', 'user.permissions'];
    $a = AccessResult::neutral()->addCacheContexts($contexts);
    $verify($a, $contexts);
    $b = AccessResult::neutral()->cachePerPermissions()->cachePerUser();
    $verify($b, $contexts);
    $c = AccessResult::neutral()->cachePerUser()->cachePerPermissions();
    $verify($c, $contexts);
    $this->assertEquals($a, $b);
    $this->assertEquals($a, $c);

    // ::allowIfHasPermission and ::allowedIfHasPermission convenience methods.
    $account = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $account->expects($this->any())
      ->method('hasPermission')
      ->with('may herd llamas')
      ->will($this->returnValue(FALSE));
    $contexts = ['user.permissions'];

    // Verify the object when using the ::allowedIfHasPermission() convenience
    // static method.
    $b = AccessResult::allowedIfHasPermission($account, 'may herd llamas');
    $verify($b, $contexts);
  }

  /**
   * @covers ::addCacheTags
   * @covers ::addCacheableDependency
   * @covers ::getCacheTags
   * @covers ::resetCacheTags
   */
  public function testCacheTags() {
    $verify = function (AccessResult $access, array $tags, array $contexts = [], $max_age = Cache::PERMANENT) {
      $this->assertFalse($access->isAllowed());
      $this->assertFalse($access->isForbidden());
      $this->assertTrue($access->isNeutral());
      $this->assertSame($max_age, $access->getCacheMaxAge());
      $this->assertSame($contexts, $access->getCacheContexts());
      $this->assertSame($tags, $access->getCacheTags());
    };

    $access = AccessResult::neutral()->addCacheTags(['foo:bar']);
    $verify($access, ['foo:bar']);
    // Verify resetting works.
    $access->resetCacheTags();
    $verify($access, []);
    // Verify idempotency.
    $access->addCacheTags(['foo:bar'])
      ->addCacheTags(['foo:bar']);
    $verify($access, ['foo:bar']);
    // Verify same values in different call order yields the same result.
    $access->resetCacheTags()
      ->addCacheTags(['bar:baz'])
      ->addCacheTags(['bar:qux'])
      ->addCacheTags(['foo:bar'])
      ->addCacheTags(['foo:baz']);
    $verify($access, ['bar:baz', 'bar:qux', 'foo:bar', 'foo:baz']);
    $access->resetCacheTags()
      ->addCacheTags(['foo:bar'])
      ->addCacheTags(['bar:qux'])
      ->addCacheTags(['foo:baz'])
      ->addCacheTags(['bar:baz']);
    $verify($access, ['bar:baz', 'bar:qux', 'foo:bar', 'foo:baz']);

    // ::addCacheableDependency() convenience method.
    $node = $this->getMock('\Drupal\node\NodeInterface');
    $node->expects($this->any())
      ->method('getCacheTags')
      ->will($this->returnValue(['node:20011988']));
    $node->expects($this->any())
      ->method('getCacheMaxAge')
      ->willReturn(600);
    $node->expects($this->any())
      ->method('getCacheContexts')
      ->willReturn(['user']);
    $tags = ['node:20011988'];
    $a = AccessResult::neutral()->addCacheTags($tags);
    $verify($a, $tags);
    $b = AccessResult::neutral()->addCacheableDependency($node);
    $verify($b, $tags, ['user'], 600);

    $non_cacheable_dependency = new \stdClass();
    $non_cacheable = AccessResult::neutral()->addCacheableDependency($non_cacheable_dependency);
    $verify($non_cacheable, [], [], 0);
  }

  /**
   * @covers ::inheritCacheability
   */
  public function testInheritCacheability() {
    // andIf(); 1st has defaults, 2nd has custom tags, contexts and max-age.
    $access = AccessResult::allowed();
    $other = AccessResult::allowed()->setCacheMaxAge(1500)->cachePerPermissions()->addCacheTags(['node:20011988']);
    $this->assertTrue($access->inheritCacheability($other) instanceof AccessResult);
    $this->assertSame(['user.permissions'], $access->getCacheContexts());
    $this->assertSame(['node:20011988'], $access->getCacheTags());
    $this->assertSame(1500, $access->getCacheMaxAge());

    // andIf(); 1st has custom tags, max-age, 2nd has custom contexts and max-age.
    $access = AccessResult::allowed()->cachePerUser()->setCacheMaxAge(43200);
    $other = AccessResult::forbidden()->addCacheTags(['node:14031991'])->setCacheMaxAge(86400);
    $this->assertTrue($access->inheritCacheability($other) instanceof AccessResult);
    $this->assertSame(['user'], $access->getCacheContexts());
    $this->assertSame(['node:14031991'], $access->getCacheTags());
    $this->assertSame(43200, $access->getCacheMaxAge());
  }

  /**
   * Provides a list of access result pairs and operations to test.
   *
   * This tests the propagation of cacheability metadata. Rather than testing
   * every single bit of cacheability metadata, which would lead to a mind-
   * boggling number of permutations, in this test, we only consider the
   * permutations of all pairs of the following set:
   * - Allowed, implements CDI and is cacheable.
   * - Allowed, implements CDI and is not cacheable.
   * - Allowed, does not implement CDI (hence not cacheable).
   * - Forbidden, implements CDI and is cacheable.
   * - Forbidden, implements CDI and is not cacheable.
   * - Forbidden, does not implement CDI (hence not cacheable).
   * - Neutral, implements CDI and is cacheable.
   * - Neutral, implements CDI and is not cacheable.
   * - Neutral, does not implement CDI (hence not cacheable).
   *
   * (Where "CDI" is CacheableDependencyInterface.)
   *
   * This leads to 72 permutations (9!/(9-2)! = 9*8 = 72) per operation. There
   * are two operations to test (AND and OR), so that leads to a grand total of
   * 144 permutations, all of which are tested.
   *
   * There are two "contagious" patterns:
   * - Any operation with a forbidden access result yields a forbidden result.
   *   This therefore also applies to the cacheability metadata associated with
   *   a forbidden result. This is the case for bullets 4, 5 and 6 in the set
   *   above.
   * - Any operation yields an access result object that is of the same class
   *   (implementation) as the first operand. This is because operations are
   *   invoked on the first operand. Therefore, if the first implementation
   *   does not implement CacheableDependencyInterface, then the result won't
   *   either. This is the case for bullets 3, 6 and 9 in the set above.
   */
  public function andOrCacheabilityPropagationProvider() {
    // ct: cacheable=true, cf: cacheable=false, un: uncacheable.
    // Note: the test cases that have a "un" access result as the first operand
    // test UncacheableTestAccessResult, not AccessResult. However, we
    // definitely want to verify that AccessResult's orIf() and andIf() methods
    // work correctly when given an AccessResultInterface implementation that
    // does not implement CacheableDependencyInterface, and we want to test the
    // full gamut of permutations, so that's not a problem.
    $allowed_ct = AccessResult::allowed();
    $allowed_cf = AccessResult::allowed()->setCacheMaxAge(0);
    $allowed_un = new UncacheableTestAccessResult('ALLOWED');
    $forbidden_ct = AccessResult::forbidden();
    $forbidden_cf = AccessResult::forbidden()->setCacheMaxAge(0);
    $forbidden_un = new UncacheableTestAccessResult('FORBIDDEN');
    $neutral_ct = AccessResult::neutral();
    $neutral_cf = AccessResult::neutral()->setCacheMaxAge(0);
    $neutral_un = new UncacheableTestAccessResult('NEUTRAL');

    // Structure:
    // - First column: first access result.
    // - Second column: operator ('OR' or 'AND').
    // - Third column: second access result.
    // - Fourth column: whether result implements CacheableDependencyInterface
    // - Fifth column: whether the result is cacheable (if column 4 is TRUE)
    return [
      // Allowed (ct) OR allowed (ct,cf,un).
      [$allowed_ct, 'OR', $allowed_ct, TRUE, TRUE],
      [$allowed_ct, 'OR', $allowed_cf, TRUE, TRUE],
      [$allowed_ct, 'OR', $allowed_un, TRUE, TRUE],
      // Allowed (cf) OR allowed (ct,cf,un).
      [$allowed_cf, 'OR', $allowed_ct, TRUE, TRUE],
      [$allowed_cf, 'OR', $allowed_cf, TRUE, FALSE],
      [$allowed_cf, 'OR', $allowed_un, TRUE, FALSE],
      // Allowed (un) OR allowed (ct,cf,un).
      [$allowed_un, 'OR', $allowed_ct, FALSE, NULL],
      [$allowed_un, 'OR', $allowed_cf, FALSE, NULL],
      [$allowed_un, 'OR', $allowed_un, FALSE, NULL],

      // Allowed (ct) OR forbidden (ct,cf,un).
      [$allowed_ct, 'OR', $forbidden_ct, TRUE, TRUE],
      [$allowed_ct, 'OR', $forbidden_cf, TRUE, FALSE],
      [$allowed_ct, 'OR', $forbidden_un, TRUE, FALSE],
      // Allowed (cf) OR forbidden (ct,cf,un).
      [$allowed_cf, 'OR', $forbidden_ct, TRUE, TRUE],
      [$allowed_cf, 'OR', $forbidden_cf, TRUE, FALSE],
      [$allowed_cf, 'OR', $forbidden_un, TRUE, FALSE],
      // Allowed (un) OR forbidden (ct,cf,un).
      [$allowed_un, 'OR', $forbidden_ct, FALSE, NULL],
      [$allowed_un, 'OR', $forbidden_cf, FALSE, NULL],
      [$allowed_un, 'OR', $forbidden_un, FALSE, NULL],

      // Allowed (ct) OR neutral (ct,cf,un).
      [$allowed_ct, 'OR', $neutral_ct, TRUE, TRUE],
      [$allowed_ct, 'OR', $neutral_cf, TRUE, TRUE],
      [$allowed_ct, 'OR', $neutral_un, TRUE, TRUE],
      // Allowed (cf) OR neutral (ct,cf,un).
      [$allowed_cf, 'OR', $neutral_ct, TRUE, FALSE],
      [$allowed_cf, 'OR', $neutral_cf, TRUE, FALSE],
      [$allowed_cf, 'OR', $neutral_un, TRUE, FALSE],
      // Allowed (un) OR neutral (ct,cf,un).
      [$allowed_un, 'OR', $neutral_ct, FALSE, NULL],
      [$allowed_un, 'OR', $neutral_cf, FALSE, NULL],
      [$allowed_un, 'OR', $neutral_un, FALSE, NULL],

      // Forbidden (ct) OR allowed (ct,cf,un).
      [$forbidden_ct, 'OR', $allowed_ct, TRUE, TRUE],
      [$forbidden_ct, 'OR', $allowed_cf, TRUE, TRUE],
      [$forbidden_ct, 'OR', $allowed_un, TRUE, TRUE],
      // Forbidden (cf) OR allowed (ct,cf,un).
      [$forbidden_cf, 'OR', $allowed_ct, TRUE, FALSE],
      [$forbidden_cf, 'OR', $allowed_cf, TRUE, FALSE],
      [$forbidden_cf, 'OR', $allowed_un, TRUE, FALSE],
      // Forbidden (un) OR allowed (ct,cf,un).
      [$forbidden_un, 'OR', $allowed_ct, FALSE, NULL],
      [$forbidden_un, 'OR', $allowed_cf, FALSE, NULL],
      [$forbidden_un, 'OR', $allowed_un, FALSE, NULL],

      // Forbidden (ct) OR neutral (ct,cf,un).
      [$forbidden_ct, 'OR', $neutral_ct, TRUE, TRUE],
      [$forbidden_ct, 'OR', $neutral_cf, TRUE, TRUE],
      [$forbidden_ct, 'OR', $neutral_un, TRUE, TRUE],
      // Forbidden (cf) OR neutral (ct,cf,un).
      [$forbidden_cf, 'OR', $neutral_ct, TRUE, FALSE],
      [$forbidden_cf, 'OR', $neutral_cf, TRUE, FALSE],
      [$forbidden_cf, 'OR', $neutral_un, TRUE, FALSE],
      // Forbidden (un) OR neutral (ct,cf,un).
      [$forbidden_un, 'OR', $neutral_ct, FALSE, NULL],
      [$forbidden_un, 'OR', $neutral_cf, FALSE, NULL],
      [$forbidden_un, 'OR', $neutral_un, FALSE, NULL],

      // Forbidden (ct) OR forbidden (ct,cf,un).
      [$forbidden_ct, 'OR', $forbidden_ct, TRUE, TRUE],
      [$forbidden_ct, 'OR', $forbidden_cf, TRUE, TRUE],
      [$forbidden_ct, 'OR', $forbidden_un, TRUE, TRUE],
      // Forbidden (cf) OR forbidden (ct,cf,un).
      [$forbidden_cf, 'OR', $forbidden_ct, TRUE, TRUE],
      [$forbidden_cf, 'OR', $forbidden_cf, TRUE, FALSE],
      [$forbidden_cf, 'OR', $forbidden_un, TRUE, FALSE],
      // Forbidden (un) OR forbidden (ct,cf,un).
      [$forbidden_un, 'OR', $forbidden_ct, FALSE, NULL],
      [$forbidden_un, 'OR', $forbidden_cf, FALSE, NULL],
      [$forbidden_un, 'OR', $forbidden_un, FALSE, NULL],

      // Neutral (ct) OR allowed (ct,cf,un).
      [$neutral_ct, 'OR', $allowed_ct, TRUE, TRUE],
      [$neutral_ct, 'OR', $allowed_cf, TRUE, FALSE],
      [$neutral_ct, 'OR', $allowed_un, TRUE, FALSE],
      // Neutral (cf) OR allowed (ct,cf,un).
      [$neutral_cf, 'OR', $allowed_ct, TRUE, TRUE],
      [$neutral_cf, 'OR', $allowed_cf, TRUE, FALSE],
      [$neutral_cf, 'OR', $allowed_un, TRUE, FALSE],
      // Neutral (un) OR allowed (ct,cf,un).
      [$neutral_un, 'OR', $allowed_ct, FALSE, NULL],
      [$neutral_un, 'OR', $allowed_cf, FALSE, NULL],
      [$neutral_un, 'OR', $allowed_un, FALSE, NULL],

      // Neutral (ct) OR neutral (ct,cf,un).
      [$neutral_ct, 'OR', $neutral_ct, TRUE, TRUE],
      [$neutral_ct, 'OR', $neutral_cf, TRUE, TRUE],
      [$neutral_ct, 'OR', $neutral_un, TRUE, TRUE],
      // Neutral (cf) OR neutral (ct,cf,un).
      [$neutral_cf, 'OR', $neutral_ct, TRUE, TRUE],
      [$neutral_cf, 'OR', $neutral_cf, TRUE, FALSE],
      [$neutral_cf, 'OR', $neutral_un, TRUE, FALSE],
      // Neutral (un) OR neutral (ct,cf,un).
      [$neutral_un, 'OR', $neutral_ct, FALSE, NULL],
      [$neutral_un, 'OR', $neutral_cf, FALSE, NULL],
      [$neutral_un, 'OR', $neutral_un, FALSE, NULL],

      // Neutral (ct) OR forbidden (ct,cf,un).
      [$neutral_ct, 'OR', $forbidden_ct, TRUE, TRUE],
      [$neutral_ct, 'OR', $forbidden_cf, TRUE, FALSE],
      [$neutral_ct, 'OR', $forbidden_un, TRUE, FALSE],
      // Neutral (cf) OR forbidden (ct,cf,un).
      [$neutral_cf, 'OR', $forbidden_ct, TRUE, TRUE],
      [$neutral_cf, 'OR', $forbidden_cf, TRUE, FALSE],
      [$neutral_cf, 'OR', $forbidden_un, TRUE, FALSE],
      // Neutral (un) OR forbidden (ct,cf,un).
      [$neutral_un, 'OR', $forbidden_ct, FALSE, NULL],
      [$neutral_un, 'OR', $forbidden_cf, FALSE, NULL],
      [$neutral_un, 'OR', $forbidden_un, FALSE, NULL],

      // Allowed (ct) AND allowed (ct,cf,un).
      [$allowed_ct, 'AND', $allowed_ct, TRUE, TRUE],
      [$allowed_ct, 'AND', $allowed_cf, TRUE, FALSE],
      [$allowed_ct, 'AND', $allowed_un, TRUE, FALSE],
      // Allowed (cf) AND allowed (ct,cf,un).
      [$allowed_cf, 'AND', $allowed_ct, TRUE, FALSE],
      [$allowed_cf, 'AND', $allowed_cf, TRUE, FALSE],
      [$allowed_cf, 'AND', $allowed_un, TRUE, FALSE],
      // Allowed (un) AND allowed (ct,cf,un).
      [$allowed_un, 'AND', $allowed_ct, FALSE, NULL],
      [$allowed_un, 'AND', $allowed_cf, FALSE, NULL],
      [$allowed_un, 'AND', $allowed_un, FALSE, NULL],

      // Allowed (ct) AND forbidden (ct,cf,un).
      [$allowed_ct, 'AND', $forbidden_ct, TRUE, TRUE],
      [$allowed_ct, 'AND', $forbidden_cf, TRUE, FALSE],
      [$allowed_ct, 'AND', $forbidden_un, TRUE, FALSE],
      // Allowed (cf) AND forbidden (ct,cf,un).
      [$allowed_cf, 'AND', $forbidden_ct, TRUE, TRUE],
      [$allowed_cf, 'AND', $forbidden_cf, TRUE, FALSE],
      [$allowed_cf, 'AND', $forbidden_un, TRUE, FALSE],
      // Allowed (un) AND forbidden (ct,cf,un).
      [$allowed_un, 'AND', $forbidden_ct, FALSE, NULL],
      [$allowed_un, 'AND', $forbidden_cf, FALSE, NULL],
      [$allowed_un, 'AND', $forbidden_un, FALSE, NULL],

      // Allowed (ct) AND neutral (ct,cf,un).
      [$allowed_ct, 'AND', $neutral_ct, TRUE, TRUE],
      [$allowed_ct, 'AND', $neutral_cf, TRUE, FALSE],
      [$allowed_ct, 'AND', $neutral_un, TRUE, FALSE],
      // Allowed (cf) AND neutral (ct,cf,un).
      [$allowed_cf, 'AND', $neutral_ct, TRUE, FALSE],
      [$allowed_cf, 'AND', $neutral_cf, TRUE, FALSE],
      [$allowed_cf, 'AND', $neutral_un, TRUE, FALSE],
      // Allowed (un) AND neutral (ct,cf,un).
      [$allowed_un, 'AND', $neutral_ct, FALSE, NULL],
      [$allowed_un, 'AND', $neutral_cf, FALSE, NULL],
      [$allowed_un, 'AND', $neutral_un, FALSE, NULL],

      // Forbidden (ct) AND allowed (ct,cf,un).
      [$forbidden_ct, 'AND', $allowed_ct, TRUE, TRUE],
      [$forbidden_ct, 'AND', $allowed_cf, TRUE, TRUE],
      [$forbidden_ct, 'AND', $allowed_un, TRUE, TRUE],
      // Forbidden (cf) AND allowed (ct,cf,un).
      [$forbidden_cf, 'AND', $allowed_ct, TRUE, FALSE],
      [$forbidden_cf, 'AND', $allowed_cf, TRUE, FALSE],
      [$forbidden_cf, 'AND', $allowed_un, TRUE, FALSE],
      // Forbidden (un) AND allowed (ct,cf,un).
      [$forbidden_un, 'AND', $allowed_ct, FALSE, NULL],
      [$forbidden_un, 'AND', $allowed_cf, FALSE, NULL],
      [$forbidden_un, 'AND', $allowed_un, FALSE, NULL],

      // Forbidden (ct) AND neutral (ct,cf,un).
      [$forbidden_ct, 'AND', $neutral_ct, TRUE, TRUE],
      [$forbidden_ct, 'AND', $neutral_cf, TRUE, TRUE],
      [$forbidden_ct, 'AND', $neutral_un, TRUE, TRUE],
      // Forbidden (cf) AND neutral (ct,cf,un).
      [$forbidden_cf, 'AND', $neutral_ct, TRUE, FALSE],
      [$forbidden_cf, 'AND', $neutral_cf, TRUE, FALSE],
      [$forbidden_cf, 'AND', $neutral_un, TRUE, FALSE],
      // Forbidden (un) AND neutral (ct,cf,un).
      [$forbidden_un, 'AND', $neutral_ct, FALSE, NULL],
      [$forbidden_un, 'AND', $neutral_cf, FALSE, NULL],
      [$forbidden_un, 'AND', $neutral_un, FALSE, NULL],

      // Forbidden (ct) AND forbidden (ct,cf,un).
      [$forbidden_ct, 'AND', $forbidden_ct, TRUE, TRUE],
      [$forbidden_ct, 'AND', $forbidden_cf, TRUE, TRUE],
      [$forbidden_ct, 'AND', $forbidden_un, TRUE, TRUE],
      // Forbidden (cf) AND forbidden (ct,cf,un).
      [$forbidden_cf, 'AND', $forbidden_ct, TRUE, FALSE],
      [$forbidden_cf, 'AND', $forbidden_cf, TRUE, FALSE],
      [$forbidden_cf, 'AND', $forbidden_un, TRUE, FALSE],
      // Forbidden (un) AND forbidden (ct,cf,un).
      [$forbidden_un, 'AND', $forbidden_ct, FALSE, NULL],
      [$forbidden_un, 'AND', $forbidden_cf, FALSE, NULL],
      [$forbidden_un, 'AND', $forbidden_un, FALSE, NULL],

      // Neutral (ct) AND allowed (ct,cf,un).
      [$neutral_ct, 'AND', $allowed_ct, TRUE, TRUE],
      [$neutral_ct, 'AND', $allowed_cf, TRUE, TRUE],
      [$neutral_ct, 'AND', $allowed_un, TRUE, TRUE],
      // Neutral (cf) AND allowed (ct,cf,un).
      [$neutral_cf, 'AND', $allowed_ct, TRUE, FALSE],
      [$neutral_cf, 'AND', $allowed_cf, TRUE, FALSE],
      [$neutral_cf, 'AND', $allowed_un, TRUE, FALSE],
      // Neutral (un) AND allowed (ct,cf,un).
      [$neutral_un, 'AND', $allowed_ct, FALSE, NULL],
      [$neutral_un, 'AND', $allowed_cf, FALSE, NULL],
      [$neutral_un, 'AND', $allowed_un, FALSE, NULL],

      // Neutral (ct) AND neutral (ct,cf,un).
      [$neutral_ct, 'AND', $neutral_ct, TRUE, TRUE],
      [$neutral_ct, 'AND', $neutral_cf, TRUE, TRUE],
      [$neutral_ct, 'AND', $neutral_un, TRUE, TRUE],
      // Neutral (cf) AND neutral (ct,cf,un).
      [$neutral_cf, 'AND', $neutral_ct, TRUE, FALSE],
      [$neutral_cf, 'AND', $neutral_cf, TRUE, FALSE],
      [$neutral_cf, 'AND', $neutral_un, TRUE, FALSE],
      // Neutral (un) AND neutral (ct,cf,un).
      [$neutral_un, 'AND', $neutral_ct, FALSE, NULL],
      [$neutral_un, 'AND', $neutral_cf, FALSE, NULL],
      [$neutral_un, 'AND', $neutral_un, FALSE, NULL],

      // Neutral (ct) AND forbidden (ct,cf,un).
      [$neutral_ct, 'AND', $forbidden_ct, TRUE, TRUE],
      [$neutral_ct, 'AND', $forbidden_cf, TRUE, FALSE],
      [$neutral_ct, 'AND', $forbidden_un, TRUE, FALSE],
      // Neutral (cf) AND forbidden (ct,cf,un).
      [$neutral_cf, 'AND', $forbidden_ct, TRUE, TRUE],
      [$neutral_cf, 'AND', $forbidden_cf, TRUE, FALSE],
      [$neutral_cf, 'AND', $forbidden_un, TRUE, FALSE],
      // Neutral (un) AND forbidden (ct,cf,un).
      [$neutral_un, 'AND', $forbidden_ct, FALSE, NULL],
      [$neutral_un, 'AND', $forbidden_cf, FALSE, NULL],
      [$neutral_un, 'AND', $forbidden_un, FALSE, NULL],
    ];
  }

  /**
   * @covers ::andIf
   * @covers ::orIf
   * @covers ::inheritCacheability
   *
   * @dataProvider andOrCacheabilityPropagationProvider
   */
  public function testAndOrCacheabilityPropagation(AccessResultInterface $first, $op, AccessResultInterface $second, $implements_cacheable_dependency_interface, $is_cacheable) {
    if ($op === 'OR') {
      $result = $first->orIf($second);
    }
    elseif ($op === 'AND') {
      $result = $first->andIf($second);
    }
    else {
      throw new \LogicException('Invalid operator specified');
    }
    if ($implements_cacheable_dependency_interface) {
      $this->assertTrue($result instanceof CacheableDependencyInterface, 'Result is an instance of CacheableDependencyInterface.');
      if ($result instanceof CacheableDependencyInterface) {
        $this->assertSame($is_cacheable, $result->getCacheMaxAge() !== 0, 'getCacheMaxAge() matches expectations.');
      }
    }
    else {
      $this->assertFalse($result instanceof CacheableDependencyInterface, 'Result is not an instance of CacheableDependencyInterface.');
    }
  }

  /**
   * @covers ::orIf
   *
   * Tests the special case of ORing non-forbidden access results that are both
   * cacheable but have different cacheability metadata.
   * This is only the case for non-forbidden access results; we still abort the
   * ORing process as soon as a forbidden access result is encountered. This is
   * tested in ::testOrIf().
   */
  public function testOrIfCacheabilityMerging() {
    $merge_both_directions = function (AccessResult $a, AccessResult $b) {
      // A globally cacheable access result.
      $a->setCacheMaxAge(3600);
      // Another access result that is cacheable per permissions.
      $b->setCacheMaxAge(86400)->cachePerPermissions();

      $r1 = $a->orIf($b);
      $this->assertTrue($r1->getCacheMaxAge() === 3600);
      $this->assertSame(['user.permissions'], $r1->getCacheContexts());
      $r2 = $b->orIf($a);
      $this->assertTrue($r2->getCacheMaxAge() === 3600);
      $this->assertSame(['user.permissions'], $r2->getCacheContexts());
    };

    // Merge either direction, get the same result.
    $merge_both_directions(AccessResult::allowed(), AccessResult::allowed());
    $merge_both_directions(AccessResult::allowed(), AccessResult::neutral());
    $merge_both_directions(AccessResult::neutral(), AccessResult::neutral());
    $merge_both_directions(AccessResult::neutral(), AccessResult::allowed());
  }

  /**
   * Tests allowedIfHasPermissions().
   *
   * @covers ::allowedIfHasPermissions
   *
   * @dataProvider providerTestAllowedIfHasPermissions
   *
   * @param string[] $permissions
   *   The permissions to check for.
   * @param string $conjunction
   *   The conjunction to use when checking for permission. 'AND' or 'OR'.
   * @param \Drupal\Core\Access\AccessResult $expected_access
   *   The expected access check result.
   */
  public function testAllowedIfHasPermissions($permissions, $conjunction, AccessResult $expected_access) {
    $account = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $account->expects($this->any())
      ->method('hasPermission')
      ->willReturnMap([
        ['allowed', TRUE],
        ['denied', FALSE],
      ]);

    if ($permissions) {
      $expected_access->cachePerPermissions();
    }

    $access_result = AccessResult::allowedIfHasPermissions($account, $permissions, $conjunction);
    $this->assertEquals($expected_access, $access_result);
  }

  /**
   * Provides data for the testAllowedIfHasPermissions() method.
   *
   * @return array
   */
  public function providerTestAllowedIfHasPermissions() {
    $access_result = AccessResult::allowedIf(FALSE);
    $data[] = [[], 'AND', $access_result];
    $data[] = [[], 'OR', $access_result];

    $access_result = AccessResult::allowedIf(TRUE);
    $data[] = [['allowed'], 'OR', $access_result];
    $data[] = [['allowed'], 'AND', $access_result];

    $access_result = AccessResult::allowedIf(FALSE);
    $access_result->setReason("The 'denied' permission is required.");
    $data[] = [['denied'], 'OR', $access_result];
    $data[] = [['denied'], 'AND', $access_result];

    $access_result = AccessResult::allowedIf(TRUE);
    $data[] = [['allowed', 'denied'], 'OR', $access_result];
    $data[] = [['denied', 'allowed'], 'OR', $access_result];

    $access_result = AccessResult::allowedIf(TRUE);
    $data[] = [['allowed', 'denied', 'other'], 'OR', $access_result];

    $access_result = AccessResult::allowedIf(FALSE);
    $access_result->setReason("The following permissions are required: 'allowed' AND 'denied'.");
    $data[] = [['allowed', 'denied'], 'AND', $access_result];

    return $data;
  }

}

class UncacheableTestAccessResult implements AccessResultInterface {

  /**
   * The access result value. 'ALLOWED', 'FORBIDDEN' or 'NEUTRAL'.
   *
   * @var string
   */
  protected $value;

  /**
   * Constructs a new UncacheableTestAccessResult object.
   */
  public function __construct($value) {
    $this->value = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed() {
    return $this->value === 'ALLOWED';
  }

  /**
   * {@inheritdoc}
   */
  public function isForbidden() {
    return $this->value === 'FORBIDDEN';
  }

  /**
   * {@inheritdoc}
   */
  public function isNeutral() {
    return $this->value === 'NEUTRAL';
  }

  /**
   * {@inheritdoc}
   */
  public function orIf(AccessResultInterface $other) {
    if ($this->isForbidden() || $other->isForbidden()) {
      return new static('FORBIDDEN');
    }
    elseif ($this->isAllowed() || $other->isAllowed()) {
      return new static('ALLOWED');
    }
    else {
      return new static('NEUTRAL');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function andIf(AccessResultInterface $other) {
    if ($this->isForbidden() || $other->isForbidden()) {
      return new static('FORBIDDEN');
    }
    elseif ($this->isAllowed() && $other->isAllowed()) {
      return new static('ALLOWED');
    }
    else {
      return new static('NEUTRAL');
    }
  }

}
