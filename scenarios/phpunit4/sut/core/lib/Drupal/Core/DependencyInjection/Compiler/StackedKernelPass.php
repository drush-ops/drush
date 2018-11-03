<?php

namespace Drupal\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Provides a compiler pass for stacked HTTP kernels.
 *
 * Builds the HTTP kernel by collecting all services tagged 'http_middleware'
 * and assembling them into a StackedKernel. The middleware with the highest
 * priority ends up as the outermost while the lowest priority middleware wraps
 * the actual HTTP kernel defined by the http_kernel.basic service.
 *
 * The 'http_middleware' service tag additionally accepts a 'responder'
 * parameter. It should be set to TRUE if many or most requests will be handled
 * directly by the middleware. Any underlying middleware and the HTTP kernel are
 * then flagged as 'lazy'. As a result those low priority services and their
 * dependencies are only initialized if the 'responder' middleware fails to
 * generate a response and the request is delegated to the underlying kernel.
 *
 * In general middlewares should not have heavy dependencies. This is especially
 * important for high-priority services which need to run before the internal
 * page cache.
 *
 * An example of a high priority middleware.
 * @code
 * http_middleware.reverse_proxy:
 *   class: Drupal\Core\StackMiddleware\ReverseProxyMiddleware
 *   arguments: ['@settings']
 *   tags:
 *     - { name: http_middleware, priority: 300 }
 * @endcode
 *
 * An example of a responder middleware:
 * @code
 * http_middleware.page_cache:
 *   class: Drupal\page_cache\StackMiddleware\PageCache
 *   arguments: ['@cache.render', '@page_cache_request_policy', '@page_cache_response_policy']
 *   tags:
 *     - { name: http_middleware, priority: 200, responder: true }
 * @endcode
 *
 * @see \Stack\Builder
 */
class StackedKernelPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container) {

    if (!$container->hasDefinition('http_kernel')) {
      return;
    }

    $stacked_kernel = $container->getDefinition('http_kernel');

    // Return now if this is not a stacked kernel.
    if ($stacked_kernel->getClass() !== 'Stack\StackedHttpKernel') {
      return;
    }

    $middlewares = [];
    $priorities = [];
    $responders = [];

    foreach ($container->findTaggedServiceIds('http_middleware') as $id => $attributes) {
      $priorities[$id] = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;
      $middlewares[$id] = $container->getDefinition($id);
      $responders[$id] = !empty($attributes[0]['responder']);
    }

    array_multisort($priorities, SORT_ASC, $middlewares, $responders);

    $decorated_id = 'http_kernel.basic';
    $middlewares_param = [new Reference($decorated_id)];

    $first_responder = array_search(TRUE, array_reverse($responders, TRUE), TRUE);
    if ($first_responder) {
      $container->getDefinition($decorated_id)->setLazy(TRUE);
    }

    foreach ($middlewares as $id => $decorator) {
      // Prepend a reference to the middlewares container parameter.
      array_unshift($middlewares_param, new Reference($id));

      // Prepend the inner kernel as first constructor argument.
      $arguments = $decorator->getArguments();
      array_unshift($arguments, new Reference($decorated_id));
      $decorator->setArguments($arguments);

      if ($first_responder === $id) {
        $first_responder = FALSE;
      }
      elseif ($first_responder) {
        $decorator->setLazy(TRUE);
      }

      $decorated_id = $id;
    }

    $arguments = [$middlewares_param[0], $middlewares_param];
    $stacked_kernel->setArguments($arguments);
  }

}
