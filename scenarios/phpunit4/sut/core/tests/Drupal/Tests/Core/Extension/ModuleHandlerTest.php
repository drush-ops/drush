<?php

namespace Drupal\Tests\Core\Extension;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Extension\ModuleHandler
 * @runTestsInSeparateProcesses
 *
 * @group Extension
 */
class ModuleHandlerTest extends UnitTestCase {

  /**
   * The mocked cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $cacheBackend;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  protected function setUp() {
    parent::setUp();
    // We can mock the cache handler here, but not the module handler.
    $this->cacheBackend = $this->getMock(CacheBackendInterface::class);
  }

  /**
   * Get a module handler object to test.
   *
   * Since we have to run these tests in separate processes, we have to use
   * test objects which are serializable. Since ModuleHandler will populate
   * itself with Extension objects, and since Extension objects will try to
   * access DRUPAL_ROOT when they're unserialized, we can't store our mocked
   * ModuleHandler objects as a property in unit tests. They must be generated
   * by the test method by calling this method.
   *
   * @return \Drupal\Core\Extension\ModuleHandler
   *   The module handler to test.
   */
  protected function getModuleHandler() {
    $module_handler = new ModuleHandler($this->root, [
      'module_handler_test' => [
        'type' => 'module',
        'pathname' => 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test/module_handler_test.info.yml',
        'filename' => 'module_handler_test.module',
      ],
    ], $this->cacheBackend);
    return $module_handler;
  }

  /**
   * Test loading a module.
   *
   * @covers ::load
   */
  public function testLoadModule() {
    $module_handler = $this->getModuleHandler();
    $this->assertFalse(function_exists('module_handler_test_hook'));
    $this->assertTrue($module_handler->load('module_handler_test'));
    $this->assertTrue(function_exists('module_handler_test_hook'));

    $module_handler->addModule('module_handler_test_added', 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test_added');
    $this->assertFalse(function_exists('module_handler_test_added_hook'), 'Function does not exist before being loaded.');
    $this->assertTrue($module_handler->load('module_handler_test_added'));
    $this->assertTrue(function_exists('module_handler_test_added_helper'), 'Function exists after being loaded.');
    $this->assertTrue($module_handler->load('module_handler_test_added'));

    $this->assertFalse($module_handler->load('module_handler_test_dne'), 'Non-existent modules returns false.');
  }

  /**
   * Test loading all modules.
   *
   * @covers ::loadAll
   */
  public function testLoadAllModules() {
    $module_handler = $this->getModuleHandler();
    $module_handler->addModule('module_handler_test_all1', 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test_all1');
    $module_handler->addModule('module_handler_test_all2', 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test_all2');
    $this->assertFalse(function_exists('module_handler_test_all1_hook'), 'Function does not exist before being loaded.');
    $this->assertFalse(function_exists('module_handler_test_all2_hook'), 'Function does not exist before being loaded.');
    $module_handler->loadAll();
    $this->assertTrue(function_exists('module_handler_test_all1_hook'), 'Function exists after being loaded.');
    $this->assertTrue(function_exists('module_handler_test_all2_hook'), 'Function exists after being loaded.');
  }

  /**
   * Test reload method.
   *
   * @covers ::reload
   */
  public function testModuleReloading() {
    $module_handler = $this->getMockBuilder(ModuleHandler::class)
      ->setConstructorArgs([
        $this->root,
        [
          'module_handler_test' => [
            'type' => 'module',
            'pathname' => 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test/module_handler_test.info.yml',
            'filename' => 'module_handler_test.module',
          ],
        ], $this->cacheBackend,
      ])
      ->setMethods(['load'])
      ->getMock();
    // First reload.
    $module_handler->expects($this->at(0))
      ->method('load')
      ->with($this->equalTo('module_handler_test'));
    // Second reload.
    $module_handler->expects($this->at(1))
      ->method('load')
      ->with($this->equalTo('module_handler_test'));
    $module_handler->expects($this->at(2))
      ->method('load')
      ->with($this->equalTo('module_handler_test_added'));
    $module_handler->reload();
    $module_handler->addModule('module_handler_test_added', 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test_added');
    $module_handler->reload();
  }

  /**
   * Test isLoaded accessor.
   *
   * @covers ::isLoaded
   */
  public function testIsLoaded() {
    $module_handler = $this->getModuleHandler();
    $this->assertFalse($module_handler->isLoaded());
    $module_handler->loadAll();
    $this->assertTrue($module_handler->isLoaded());
  }

  /**
   * Confirm we get back the modules set in the constructor.
   *
   * @covers ::getModuleList
   */
  public function testGetModuleList() {
    $this->assertEquals($this->getModuleHandler()->getModuleList(), [
      'module_handler_test' => new Extension($this->root, 'module', 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test/module_handler_test.info.yml', 'module_handler_test.module'),
    ]);
  }

  /**
   * Confirm we get back a module from the module list
   *
   * @covers ::getModule
   */
  public function testGetModuleWithExistingModule() {
    $this->assertEquals($this->getModuleHandler()->getModule('module_handler_test'), new Extension($this->root, 'module', 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test/module_handler_test.info.yml', 'module_handler_test.module'));
  }

  /**
   * @covers ::getModule
   */
  public function testGetModuleWithNonExistingModule() {
    $this->setExpectedException(UnknownExtensionException::class);
    $this->getModuleHandler()->getModule('claire_alice_watch_my_little_pony_module_that_does_not_exist');
  }

  /**
   * Ensure setting the module list replaces the module list and resets internal structures.
   *
   * @covers ::setModuleList
   */
  public function testSetModuleList() {
    $fixture_module_handler = $this->getModuleHandler();
    $module_handler = $this->getMockBuilder(ModuleHandler::class)
      ->setConstructorArgs([
        $this->root, [], $this->cacheBackend,
      ])
      ->setMethods(['resetImplementations'])
      ->getMock();

    // Ensure we reset implementations when settings a new modules list.
    $module_handler->expects($this->once())->method('resetImplementations');

    // Make sure we're starting empty.
    $this->assertEquals($module_handler->getModuleList(), []);

    // Replace the list with a prebuilt list.
    $module_handler->setModuleList($fixture_module_handler->getModuleList());

    // Ensure those changes are stored.
    $this->assertEquals($fixture_module_handler->getModuleList(), $module_handler->getModuleList());
  }

  /**
   * Test adding a module.
   *
   * @covers ::addModule
   * @covers ::add
   */
  public function testAddModule() {

    $module_handler = $this->getMockBuilder(ModuleHandler::class)
      ->setConstructorArgs([
        $this->root, [], $this->cacheBackend,
      ])
      ->setMethods(['resetImplementations'])
      ->getMock();

    // Ensure we reset implementations when settings a new modules list.
    $module_handler->expects($this->once())->method('resetImplementations');

    $module_handler->addModule('module_handler_test', 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test');
    $this->assertTrue($module_handler->moduleExists('module_handler_test'));
  }

  /**
   * Test adding a profile.
   *
   * @covers ::addProfile
   * @covers ::add
   */
  public function testAddProfile() {

    $module_handler = $this->getMockBuilder(ModuleHandler::class)
      ->setConstructorArgs([
        $this->root, [], $this->cacheBackend,
      ])
      ->setMethods(['resetImplementations'])
      ->getMock();

    // Ensure we reset implementations when settings a new modules list.
    $module_handler->expects($this->once())->method('resetImplementations');

    // @todo this should probably fail since its a module not a profile.
    $module_handler->addProfile('module_handler_test', 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test');
    $this->assertTrue($module_handler->moduleExists('module_handler_test'));
  }

  /**
   * Test module exists returns correct module status.
   *
   * @covers ::moduleExists
   */
  public function testModuleExists() {
    $module_handler = $this->getModuleHandler();
    $this->assertTrue($module_handler->moduleExists('module_handler_test'));
    $this->assertFalse($module_handler->moduleExists('module_handler_test_added'));
  }

  /**
   * @covers ::loadAllIncludes
   */
  public function testLoadAllIncludes() {
    $this->assertTrue(TRUE);
    $module_handler = $this->getMockBuilder(ModuleHandler::class)
      ->setConstructorArgs([
        $this->root,
        [
          'module_handler_test' => [
            'type' => 'module',
            'pathname' => 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test/module_handler_test.info.yml',
            'filename' => 'module_handler_test.module',
          ],
        ], $this->cacheBackend,
      ])
      ->setMethods(['loadInclude'])
      ->getMock();

    // Ensure we reset implementations when settings a new modules list.
    $module_handler->expects($this->once())->method('loadInclude');
    $module_handler->loadAllIncludes('hook');
  }

  /**
   * @covers ::loadInclude
   *
   * Note we load code, so isolate the test.
   *
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   */
  public function testLoadInclude() {
    $module_handler = $this->getModuleHandler();
    // Include exists.
    $this->assertEquals(__DIR__ . '/modules/module_handler_test/hook_include.inc', $module_handler->loadInclude('module_handler_test', 'inc', 'hook_include'));
    $this->assertTrue(function_exists('module_handler_test_hook_include'));
    // Include doesn't exist.
    $this->assertFalse($module_handler->loadInclude('module_handler_test', 'install'));
  }

  /**
   * Test invoke methods when module is enabled.
   *
   * @covers ::invoke
   */
  public function testInvokeModuleEnabled() {
    $module_handler = $this->getModuleHandler();
    $this->assertTrue($module_handler->invoke('module_handler_test', 'hook', [TRUE]), 'Installed module runs hook.');
    $this->assertFalse($module_handler->invoke('module_handler_test', 'hook', [FALSE]), 'Installed module runs hook.');
    $this->assertNull($module_handler->invoke('module_handler_test_fake', 'hook', [FALSE]), 'Installed module runs hook.');
  }

  /**
   * Test implementations methods when module is enabled.
   *
   * @covers ::implementsHook
   * @covers ::loadAllIncludes
   */
  public function testImplementsHookModuleEnabled() {
    $module_handler = $this->getModuleHandler();
    $this->assertTrue($module_handler->implementsHook('module_handler_test', 'hook'), 'Installed module implementation found.');

    $module_handler->addModule('module_handler_test_added', 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test_added');
    $this->assertTrue($module_handler->implementsHook('module_handler_test_added', 'hook'), 'Runtime added module with implementation in include found.');

    $module_handler->addModule('module_handler_test_no_hook', 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test_added');
    $this->assertFalse($module_handler->implementsHook('module_handler_test_no_hook', 'hook', [TRUE]), 'Missing implementation not found.');
  }

  /**
   * Test getImplementations.
   *
   * @covers ::getImplementations
   * @covers ::getImplementationInfo
   * @covers ::buildImplementationInfo
   */
  public function testGetImplementations() {
    $this->assertEquals(['module_handler_test'], $this->getModuleHandler()->getImplementations('hook'));
  }

  /**
   * Test getImplementations.
   *
   * @covers ::getImplementations
   * @covers ::getImplementationInfo
   */
  public function testCachedGetImplementations() {
    $this->cacheBackend->expects($this->exactly(1))
      ->method('get')
      ->will($this->onConsecutiveCalls(
        (object) ['data' => ['hook' => ['module_handler_test' => 'test']]]
      ));

    // Ensure buildImplementationInfo doesn't get called and that we work off cached results.
    $module_handler = $this->getMockBuilder(ModuleHandler::class)
      ->setConstructorArgs([
        $this->root, [
          'module_handler_test' => [
            'type' => 'module',
            'pathname' => 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test/module_handler_test.info.yml',
            'filename' => 'module_handler_test.module',
          ],
        ], $this->cacheBackend,
      ])
      ->setMethods(['buildImplementationInfo', 'loadInclude'])
      ->getMock();
    $module_handler->load('module_handler_test');

    $module_handler->expects($this->never())->method('buildImplementationInfo');
    $module_handler->expects($this->once())->method('loadInclude');
    $this->assertEquals(['module_handler_test'], $module_handler->getImplementations('hook'));
  }

  /**
   * Test getImplementations.
   *
   * @covers ::getImplementations
   * @covers ::getImplementationInfo
   */
  public function testCachedGetImplementationsMissingMethod() {
    $this->cacheBackend->expects($this->exactly(1))
      ->method('get')
      ->will($this->onConsecutiveCalls((object) [
        'data' => [
          'hook' => [
            'module_handler_test' => [],
            'module_handler_test_missing' => [],
          ],
        ],
      ]));

    // Ensure buildImplementationInfo doesn't get called and that we work off cached results.
    $module_handler = $this->getMockBuilder(ModuleHandler::class)
      ->setConstructorArgs([
        $this->root, [
          'module_handler_test' => [
            'type' => 'module',
            'pathname' => 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test/module_handler_test.info.yml',
            'filename' => 'module_handler_test.module',
          ],
        ], $this->cacheBackend,
      ])
      ->setMethods(['buildImplementationInfo'])
      ->getMock();
    $module_handler->load('module_handler_test');

    $module_handler->expects($this->never())->method('buildImplementationInfo');
    $this->assertEquals(['module_handler_test'], $module_handler->getImplementations('hook'));
  }

  /**
   * Test invoke all.
   *
   * @covers ::invokeAll
   */
  public function testInvokeAll() {
    $module_handler = $this->getModuleHandler();
    $module_handler->addModule('module_handler_test_all1', 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test_all1');
    $module_handler->addModule('module_handler_test_all2', 'core/tests/Drupal/Tests/Core/Extension/modules/module_handler_test_all2');
    $this->assertEquals([TRUE, TRUE, TRUE], $module_handler->invokeAll('hook', [TRUE]));
  }

  /**
   * Test that write cache calls through to cache library correctly.
   *
   * @covers ::writeCache
   */
  public function testWriteCache() {
    $module_handler = $this->getModuleHandler();
    $this->cacheBackend
      ->expects($this->exactly(2))
      ->method('get')
      ->will($this->returnValue(NULL));
    $this->cacheBackend
      ->expects($this->exactly(2))
      ->method('set')
      ->with($this->logicalOr('module_implements', 'hook_info'));
    $module_handler->getImplementations('hook');
    $module_handler->writeCache();
  }

  /**
   * Test hook_hook_info() fetching through getHookInfo().
   *
   * @covers ::getHookInfo
   * @covers ::buildHookInfo
   */
  public function testGetHookInfo() {
    $module_handler = $this->getModuleHandler();
    // Set up some synthetic results.
    $this->cacheBackend
      ->expects($this->exactly(2))
      ->method('get')
      ->will($this->onConsecutiveCalls(
        NULL,
        (object) ['data' => ['hook_foo' => ['group' => 'hook']]]
      ));

    // Results from building from mocked environment.
    $this->assertEquals([
      'hook' => ['group' => 'hook'],
    ], $module_handler->getHookInfo());

    // Reset local cache so we get our synthetic result from the cache handler.
    $module_handler->resetImplementations();
    $this->assertEquals([
      'hook_foo' => ['group' => 'hook'],
    ], $module_handler->getHookInfo());
  }

  /**
   * Test internal implementation cache reset.
   *
   * @covers ::resetImplementations
   */
  public function testResetImplementations() {
    $module_handler = $this->getModuleHandler();
    // Prime caches
    $module_handler->getImplementations('hook');
    $module_handler->getHookInfo();

    // Reset all caches internal and external.
    $this->cacheBackend
      ->expects($this->once())
      ->method('delete')
      ->with('hook_info');
    $this->cacheBackend
      ->expects($this->exactly(2))
      ->method('set')
      // reset sets module_implements to array() and getHookInfo later
      // populates hook_info.
      ->with($this->logicalOr('module_implements', 'hook_info'));
    $module_handler->resetImplementations();

    // Request implementation and ensure hook_info and module_implements skip
    // local caches.
    $this->cacheBackend
      ->expects($this->exactly(2))
      ->method('get')
      ->with($this->logicalOr('module_implements', 'hook_info'));
    $module_handler->getImplementations('hook');
  }

  /**
   * @dataProvider dependencyProvider
   * @covers ::parseDependency
   */
  public function testDependencyParsing($dependency, $expected) {
    $version = ModuleHandler::parseDependency($dependency);
    $this->assertEquals($expected, $version);
  }

  /**
   * Provider for testing dependency parsing.
   */
  public function dependencyProvider() {
    return [
      ['system', ['name' => 'system']],
      ['taxonomy', ['name' => 'taxonomy']],
      ['views', ['name' => 'views']],
      ['views_ui(8.x-1.0)', ['name' => 'views_ui', 'original_version' => ' (8.x-1.0)', 'versions' => [['op' => '=', 'version' => '1.0']]]],
      // Not supported?.
      // array('views_ui(8.x-1.1-beta)', array('name' => 'views_ui', 'original_version' => ' (8.x-1.1-beta)', 'versions' => array(array('op' => '=', 'version' => '1.1-beta')))),
      ['views_ui(8.x-1.1-alpha12)', ['name' => 'views_ui', 'original_version' => ' (8.x-1.1-alpha12)', 'versions' => [['op' => '=', 'version' => '1.1-alpha12']]]],
      ['views_ui(8.x-1.1-beta8)', ['name' => 'views_ui', 'original_version' => ' (8.x-1.1-beta8)', 'versions' => [['op' => '=', 'version' => '1.1-beta8']]]],
      ['views_ui(8.x-1.1-rc11)', ['name' => 'views_ui', 'original_version' => ' (8.x-1.1-rc11)', 'versions' => [['op' => '=', 'version' => '1.1-rc11']]]],
      ['views_ui(8.x-1.12)', ['name' => 'views_ui', 'original_version' => ' (8.x-1.12)', 'versions' => [['op' => '=', 'version' => '1.12']]]],
      ['views_ui(8.x-1.x)', ['name' => 'views_ui', 'original_version' => ' (8.x-1.x)', 'versions' => [['op' => '<', 'version' => '2.x'], ['op' => '>=', 'version' => '1.x']]]],
      ['views_ui( <= 8.x-1.x)', ['name' => 'views_ui', 'original_version' => ' ( <= 8.x-1.x)', 'versions' => [['op' => '<=', 'version' => '2.x']]]],
      ['views_ui(<= 8.x-1.x)', ['name' => 'views_ui', 'original_version' => ' (<= 8.x-1.x)', 'versions' => [['op' => '<=', 'version' => '2.x']]]],
      ['views_ui( <=8.x-1.x)', ['name' => 'views_ui', 'original_version' => ' ( <=8.x-1.x)', 'versions' => [['op' => '<=', 'version' => '2.x']]]],
      ['views_ui(>8.x-1.x)', ['name' => 'views_ui', 'original_version' => ' (>8.x-1.x)', 'versions' => [['op' => '>', 'version' => '2.x']]]],
      ['drupal:views_ui(>8.x-1.x)', ['project' => 'drupal', 'name' => 'views_ui', 'original_version' => ' (>8.x-1.x)', 'versions' => [['op' => '>', 'version' => '2.x']]]],
    ];
  }

  /**
   * @covers ::getModuleDirectories
   */
  public function testGetModuleDirectories() {
    $module_handler = $this->getModuleHandler();
    $module_handler->setModuleList([]);
    $module_handler->addModule('module', 'place');
    $this->assertEquals(['module' => $this->root . '/place'], $module_handler->getModuleDirectories());
  }

}
