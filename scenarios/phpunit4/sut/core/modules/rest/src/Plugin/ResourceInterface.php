<?php

namespace Drupal\rest\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Specifies the publicly available methods of a resource plugin.
 *
 * @see \Drupal\rest\Annotation\RestResource
 * @see \Drupal\rest\Plugin\Type\ResourcePluginManager
 * @see \Drupal\rest\Plugin\ResourceBase
 * @see plugin_api
 *
 * @ingroup third_party
 */
interface ResourceInterface extends PluginInspectionInterface {

  /**
   * Returns a collection of routes with URL path information for the resource.
   *
   * This method determines where a resource is reachable, what path
   * replacements are used, the required HTTP method for the operation etc.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   A collection of routes that should be registered for this resource.
   */
  public function routes();

  /**
   * Provides an array of permissions suitable for .permissions.yml files.
   *
   * A resource plugin can define a set of user permissions that are used on the
   * routes for this resource or for other purposes.
   *
   * It is not required for a resource plugin to specify permissions: if they
   * have their own access control mechanism, they can use that, and return the
   * empty array.
   *
   * @return array
   *   The permission array.
   */
  public function permissions();

  /**
   * Returns the available HTTP request methods on this plugin.
   *
   * @return array
   *   The list of supported methods. Example: array('GET', 'POST', 'PATCH').
   */
  public function availableMethods();

}
