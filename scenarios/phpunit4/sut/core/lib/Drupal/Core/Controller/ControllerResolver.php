<?php

namespace Drupal\Core\Controller;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolver as BaseControllerResolver;

/**
 * ControllerResolver to enhance controllers beyond Symfony's basic handling.
 *
 * It adds two behaviors:
 *
 *  - When creating a new object-based controller that implements
 *    ContainerAwareInterface, inject the container into it. While not always
 *    necessary, that allows a controller to vary the services it needs at
 *    runtime.
 *
 *  - By default, a controller name follows the class::method notation. This
 *    class adds the possibility to use a service from the container as a
 *    controller by using a service:method notation (Symfony uses the same
 *    convention).
 */
class ControllerResolver extends BaseControllerResolver implements ControllerResolverInterface {

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The PSR-7 converter.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
   */
  protected $httpMessageFactory;

  /**
   * Constructs a new ControllerResolver.
   *
   * @param \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface $http_message_factory
   *   The PSR-7 converter.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   */
  public function __construct(HttpMessageFactoryInterface $http_message_factory, ClassResolverInterface $class_resolver) {
    $this->httpMessageFactory = $http_message_factory;
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function getControllerFromDefinition($controller, $path = '') {
    if (is_array($controller) || (is_object($controller) && method_exists($controller, '__invoke'))) {
      return $controller;
    }

    if (strpos($controller, ':') === FALSE) {
      if (function_exists($controller)) {
        return $controller;
      }
      elseif (method_exists($controller, '__invoke')) {
        return new $controller();
      }
    }

    $callable = $this->createController($controller);

    if (!is_callable($callable)) {
      throw new \InvalidArgumentException(sprintf('The controller for URI "%s" is not callable.', $path));
    }

    return $callable;
  }

  /**
   * {@inheritdoc}
   */
  public function getController(Request $request) {
    if (!$controller = $request->attributes->get('_controller')) {
      return FALSE;
    }
    return $this->getControllerFromDefinition($controller, $request->getPathInfo());
  }

  /**
   * Returns a callable for the given controller.
   *
   * @param string $controller
   *   A Controller string.
   *
   * @return mixed
   *   A PHP callable.
   *
   * @throws \LogicException
   *   If the controller cannot be parsed.
   *
   * @throws \InvalidArgumentException
   *   If the controller class does not exist.
   */
  protected function createController($controller) {
    // Controller in the service:method notation.
    $count = substr_count($controller, ':');
    if ($count == 1) {
      list($class_or_service, $method) = explode(':', $controller, 2);
    }
    // Controller in the class::method notation.
    elseif (strpos($controller, '::') !== FALSE) {
      list($class_or_service, $method) = explode('::', $controller, 2);
    }
    else {
      throw new \LogicException(sprintf('Unable to parse the controller name "%s".', $controller));
    }

    $controller = $this->classResolver->getInstanceFromDefinition($class_or_service);

    return [$controller, $method];
  }

  /**
   * {@inheritdoc}
   */
  protected function doGetArguments(Request $request, $controller, array $parameters) {
    // Note this duplicates the deprecation message of
    // Symfony\Component\HttpKernel\Controller\ControllerResolver::getArguments()
    // to ensure it is removed in Drupal 9.
    @trigger_error(sprintf('%s is deprecated as of 8.6.0 and will be removed in 9.0. Inject the "http_kernel.controller.argument_resolver" service instead.', __METHOD__, ArgumentResolverInterface::class), E_USER_DEPRECATED);
    $attributes = $request->attributes->all();
    $raw_parameters = $request->attributes->has('_raw_variables') ? $request->attributes->get('_raw_variables') : [];
    $arguments = [];
    foreach ($parameters as $param) {
      if (array_key_exists($param->name, $attributes)) {
        $arguments[] = $attributes[$param->name];
      }
      elseif (array_key_exists($param->name, $raw_parameters)) {
        $arguments[] = $attributes[$param->name];
      }
      elseif ($param->getClass() && $param->getClass()->isInstance($request)) {
        $arguments[] = $request;
      }
      elseif ($param->getClass() && $param->getClass()->name === ServerRequestInterface::class) {
        $arguments[] = $this->httpMessageFactory->createRequest($request);
      }
      elseif ($param->getClass() && ($param->getClass()->name == RouteMatchInterface::class || is_subclass_of($param->getClass()->name, RouteMatchInterface::class))) {
        $arguments[] = RouteMatch::createFromRequest($request);
      }
      elseif ($param->isDefaultValueAvailable()) {
        $arguments[] = $param->getDefaultValue();
      }
      else {
        if (is_array($controller)) {
          $repr = sprintf('%s::%s()', get_class($controller[0]), $controller[1]);
        }
        elseif (is_object($controller)) {
          $repr = get_class($controller);
        }
        else {
          $repr = $controller;
        }

        throw new \RuntimeException(sprintf('Controller "%s" requires that you provide a value for the "$%s" argument (because there is no default value or because there is a non optional argument after this one).', $repr, $param->name));
      }
    }
    return $arguments;
  }

}
