<?php

namespace Drupal\Tests\devel\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\devel\Twig\Extension\Debug;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests Twig extensions.
 *
 * @group devel
 */
class DevelTwigExtensionTest extends KernelTestBase {

  use DevelDumperTestTrait;

  /**
   * The user used in test.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $develUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['devel', 'user', 'system'];


  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('system', 'sequences');

    $devel_role = Role::create([
      'id' => 'admin',
      'permissions' => ['access devel information'],
    ]);
    $devel_role->save();

    $this->develUser = User::create([
      'name' => $this->randomMachineName(),
      'roles' => [$devel_role->id()],
    ]);
    $this->develUser->save();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    $parameters = $container->getParameter('twig.config');
    $parameters['debug'] = TRUE;
    $container->setParameter('twig.config', $parameters);
  }

  /**
   * Tests that Twig extension loads appropriately.
   */
  public function testTwigExtensionLoaded() {
    $twig_service = \Drupal::service('twig');
    $extension = $twig_service->getExtension('devel_debug');
    $this->assertEquals(get_class($extension), Debug::class, 'Debug Extension loaded successfully.');
  }

  /**
   * Tests that the Twig dump functions are registered properly.
   */
  public function testDumpFunctionsRegistered() {
    /** @var \Twig_SimpleFunction[] $functions */
    $functions = \Drupal::service('twig')->getFunctions();

    $dump_functions = ['devel_dump', 'kpr'];
    $message_functions = ['devel_message', 'dpm', 'dsm'];
    $registered_functions = $dump_functions + $message_functions;

    foreach ($registered_functions as $name) {
      $function = $functions[$name];
      $this->assertTrue($function instanceof \Twig_SimpleFunction);
      $this->assertEquals($function->getName(), $name);
      $this->assertTrue($function->needsContext());
      $this->assertTrue($function->needsEnvironment());
      $this->assertTrue($function->isVariadic());

      is_callable($function->getCallable(), TRUE, $callable);
      if (in_array($name, $dump_functions)) {
        $this->assertEquals($callable, 'Drupal\devel\Twig\Extension\Debug::dump');
      }
      else {
        $this->assertEquals($callable, 'Drupal\devel\Twig\Extension\Debug::message');
      }
    }
  }

  /**
   * Tests that the Twig function for XDebug integration is registered properly.
   */
  public function testXDebugIntegrationFunctionsRegistered() {
    /** @var \Twig_SimpleFunction $function */
    $function = \Drupal::service('twig')->getFunction('devel_breakpoint');
    $this->assertTrue($function instanceof \Twig_SimpleFunction);
    $this->assertEquals($function->getName(), 'devel_breakpoint');
    $this->assertTrue($function->needsContext());
    $this->assertTrue($function->needsEnvironment());
    $this->assertTrue($function->isVariadic());
    is_callable($function->getCallable(), TRUE, $callable);
    $this->assertEquals($callable, 'Drupal\devel\Twig\Extension\Debug::breakpoint');
  }

  /**
   * Tests that the Twig extension's dump functions produce the expected output.
   */
  public function testDumpFunctions() {
    $template = 'test-with-context {{ twig_string }} {{ twig_array.first }} {{ twig_array.second }}{{ devel_dump() }}';
    $expected_template_output = 'test-with-context context! first value second value';

    $context = [
      'twig_string' => 'context!',
      'twig_array' => [
        'first' => 'first value',
        'second' => 'second value',
      ],
      'twig_object' => new \stdClass(),
    ];

    /** @var \Drupal\Core\Template\TwigEnvironment $environment */
    $environment = \Drupal::service('twig');

    // Ensures that the twig extension does nothing if the current
    // user has not the adequate permission.
    $this->assertTrue($environment->isDebug());
    $this->assertEquals($environment->renderInline($template, $context), $expected_template_output);

    \Drupal::currentUser()->setAccount($this->develUser);

    // Ensures that if no argument is passed to the function the twig context is
    // dumped.
    $output = (string) $environment->renderInline($template, $context);
    $this->assertContains($expected_template_output,  $output);
    $this->assertContainsDump($output, $context, 'Twig context');

    // Ensures that if an argument is passed to the function it is dumped.
    $template = 'test-with-context {{ twig_string }} {{ twig_array.first }} {{ twig_array.second }}{{ devel_dump(twig_array) }}';
    $output = (string) $environment->renderInline($template, $context);
    $this->assertContains($expected_template_output, $output);
    $this->assertContainsDump($output, $context['twig_array']);

    // Ensures that if more than one argument is passed the function works
    // properly and every argument is dumped separately.
    $template = 'test-with-context {{ twig_string }} {{ twig_array.first }} {{ twig_array.second }}{{ devel_dump(twig_string, twig_array.first, twig_array, twig_object) }}';
    $output = (string) $environment->renderInline($template, $context);
    $this->assertContains($expected_template_output, $output);
    $this->assertContainsDump($output, $context['twig_string']);
    $this->assertContainsDump($output, $context['twig_array']['first']);
    $this->assertContainsDump($output, $context['twig_array']);
    $this->assertContainsDump($output, $context['twig_object']);

    // Clear messages.
    drupal_get_messages();

    $retrieve_message = function ($messages, $index) {
      return isset($messages['status'][$index]) ? (string) $messages['status'][$index] : NULL;
    };

    // Ensures that if no argument is passed to the function the twig context is
    // dumped.
    $template = 'test-with-context {{ twig_string }} {{ twig_array.first }} {{ twig_array.second }}{{ devel_message() }}';
    $output = (string) $environment->renderInline($template, $context);
    $this->assertContains($expected_template_output, $output);
    $messages = drupal_get_messages();
    $this->assertDumpExportEquals($retrieve_message($messages, 0), $context, 'Twig context');

    // Ensures that if an argument is passed to the function it is dumped.
    $template = 'test-with-context {{ twig_string }} {{ twig_array.first }} {{ twig_array.second }}{{ devel_message(twig_array) }}';
    $output = (string) $environment->renderInline($template, $context);
    $this->assertContains($expected_template_output, $output);
    $messages = drupal_get_messages();
    $this->assertDumpExportEquals($retrieve_message($messages, 0), $context['twig_array']);

    // Ensures that if more than one argument is passed to the function works
    // properly and every argument is dumped separately.
    $template = 'test-with-context {{ twig_string }} {{ twig_array.first }} {{ twig_array.second }}{{ devel_message(twig_string, twig_array.first, twig_array, twig_object) }}';
    $output = (string) $environment->renderInline($template, $context);
    $this->assertContains($expected_template_output, $output);
    $messages = drupal_get_messages();
    $this->assertDumpExportEquals($retrieve_message($messages, 0), $context['twig_string']);
    $this->assertDumpExportEquals($retrieve_message($messages, 1), $context['twig_array']['first']);
    $this->assertDumpExportEquals($retrieve_message($messages, 2), $context['twig_array']);
    $this->assertDumpExportEquals($retrieve_message($messages, 3), $context['twig_object']);
  }

}
