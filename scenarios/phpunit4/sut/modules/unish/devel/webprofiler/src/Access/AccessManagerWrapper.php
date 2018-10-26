<?php

namespace Drupal\webprofiler\Access;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\AccessManager;
use Drupal\Component\Utility\ArgumentsResolverInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\webprofiler\DataCollector\RequestDataCollector;
use Symfony\Component\HttpFoundation\Request;

/**
 * Attaches access check services to routes and runs them on request.
 *
 * @see \Drupal\Tests\Core\Access\AccessManagerTest
 */
class AccessManagerWrapper extends AccessManager {

  /**
   * @var \Drupal\webprofiler\DataCollector\RequestDataCollector
   */
  private $dataCollector;

  /**
   * {@inheritdoc}
   */
  public function check(RouteMatchInterface $route_match, AccountInterface $account = NULL, Request $request = NULL, $return_as_object = FALSE) {
    if (!isset($account)) {
      $account = $this->currentUser;
    }
    $route = $route_match->getRouteObject();
    $checks = $route->getOption('_access_checks') ?: array();

    // Filter out checks which require the incoming request.
    if (!isset($request)) {
      $checks = array_diff($checks, $this->checkProvider->getChecksNeedRequest());
    }

    $result = AccessResult::neutral();
    if (!empty($checks)) {
      $arguments_resolver = $this->argumentsResolverFactory->getArgumentsResolver($route_match, $account, $request);

      if (!$checks) {
        return AccessResult::neutral();
      }
      $result = AccessResult::allowed();
      foreach ($checks as $service_id) {
        $result = $result->andIf($this->performCheck($service_id, $arguments_resolver, $request));
      }
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  protected function performCheck($service_id, ArgumentsResolverInterface $arguments_resolver, Request $request = NULL) {
    $callable = $this->checkProvider->loadCheck($service_id);
    $arguments = $arguments_resolver->getArguments($callable);
    /** @var \Drupal\Core\Access\AccessResultInterface $service_access **/
    $service_access = call_user_func_array($callable, $arguments);

    if (!$service_access instanceof AccessResultInterface) {
      throw new AccessException("Access error in $service_id. Access services must return an object that implements AccessResultInterface.");
    }

    if($request) {
      $this->dataCollector->addAccessCheck($service_id, $callable, $request);
    }

    return $service_access;
  }

  /**
   * @param \Drupal\webprofiler\DataCollector\RequestDataCollector $dataCollector
   */
  public function setDataCollector(RequestDataCollector $dataCollector) {
    $this->dataCollector = $dataCollector;
  }
}
