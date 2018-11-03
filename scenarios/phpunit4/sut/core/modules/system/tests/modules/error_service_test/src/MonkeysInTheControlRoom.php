<?php

namespace Drupal\error_service_test;

use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * A http middleware designed to cause bedlam.
 *
 * @see error_service_test.services.yml
 */
class MonkeysInTheControlRoom implements HttpKernelInterface {

  /**
   * The app kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $app;

  /**
   * MonkeysInTheControlRoom constructor.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $app
   *   The wrapper HTTP kernel.
   */
  public function __construct(HttpKernelInterface $app, Settings $settings) {
    $this->app = $app;
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    if (\Drupal::state()->get('error_service_test.break_bare_html_renderer')) {
      // Let the bedlam begin.
      // 1) Force a container rebuild.
      /** @var \Drupal\Core\DrupalKernelInterface $kernel */
      $kernel = \Drupal::service('kernel');
      $kernel->rebuildContainer();
      // 2) Fetch the in-situ container builder.
      $container = ErrorServiceTestServiceProvider::$containerBuilder;
      // Ensure the compiler pass worked.
      if (!$container) {
        throw new \Exception('Oh oh, monkeys stole the ServiceProvider.');
      }
      // Stop the theme manager from being found - and triggering error
      // maintenance mode.
      $container->removeDefinition('theme.manager');
      // Mash. Mash. Mash.
      \Drupal::setContainer($container);
      throw new \Exception('Oh oh, bananas in the instruments.');
    }

    if (\Drupal::state()->get('error_service_test.break_logger')) {
      throw new \Exception('Deforestation');
    }

    if ($this->settings->get('teapots', FALSE) && class_exists('\TypeError')) {
      try {
        $return = $this->app->handle($request, $type, $catch);
      }
      catch (\TypeError $e) {
        header('HTTP/1.1 418 I\'m a teapot');
        print('Oh oh, flying teapots');
        exit;
      }
    }
    else {
      $return = $this->app->handle($request, $type, $catch);
    }
    return $return;
  }

}
