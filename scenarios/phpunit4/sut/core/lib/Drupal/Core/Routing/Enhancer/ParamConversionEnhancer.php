<?php

namespace Drupal\Core\Routing\Enhancer;

use Drupal\Core\ParamConverter\ParamConverterManagerInterface;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\Core\Routing\EnhancerInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Provides a route enhancer that handles parameter conversion.
 */
class ParamConversionEnhancer implements EnhancerInterface, EventSubscriberInterface {

  /**
   * The parameter conversion manager.
   *
   * @var \Drupal\Core\ParamConverter\ParamConverterManagerInterface
   */
  protected $paramConverterManager;

  /**
   * Constructs a new ParamConversionEnhancer.
   *
   * @param \Drupal\Core\ParamConverter\ParamConverterManagerInterface $param_converter_manager
   *   The parameter conversion manager.
   */
  public function __construct(ParamConverterManagerInterface $param_converter_manager) {
    $this->paramConverterManager = $param_converter_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    // Just run the parameter conversion once per request.
    if (!isset($defaults['_raw_variables'])) {
      $defaults['_raw_variables'] = $this->copyRawVariables($defaults);
      $defaults = $this->paramConverterManager->convert($defaults);
    }
    return $defaults;
  }

  /**
   * Store a backup of the raw values that corresponding to the route pattern.
   *
   * @param array $defaults
   *   The route defaults array.
   *
   * @return \Symfony\Component\HttpFoundation\ParameterBag
   */
  protected function copyRawVariables(array $defaults) {
    /** @var $route \Symfony\Component\Routing\Route */
    $route = $defaults[RouteObjectInterface::ROUTE_OBJECT];
    $variables = array_flip($route->compile()->getVariables());
    // Foreach will copy the values from the array it iterates. Even if they
    // are references, use it to break them. This avoids any scenarios where raw
    // variables also get replaced with converted values.
    $raw_variables = [];
    foreach (array_intersect_key($defaults, $variables) as $key => $value) {
      $raw_variables[$key] = $value;
    }
    return new ParameterBag($raw_variables);
  }

  /**
   * Catches failed parameter conversions and throw a 404 instead.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
   */
  public function onException(GetResponseForExceptionEvent $event) {
    $exception = $event->getException();
    if ($exception instanceof ParamNotConvertedException) {
      $event->setException(new NotFoundHttpException($exception->getMessage(), $exception));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::EXCEPTION][] = ['onException', 75];
    return $events;
  }

}
