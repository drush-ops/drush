<?php

namespace Drush\Boot;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class BootstrapManager implements LoggerAwareInterface {
  use LoggerAwareTrait;

  /**
   * @var Drush\Boot\Boot[]
   */
  protected $bootstrapCandidates = [];

  /**
   * @var Drush\Boot\Boot
   */
  protected $defaultBootstrapObject;

  /**
   * @var Drush\Boot\Boot
   */
  protected $bootstrap;

  /**
   * @var string
   */
  protected $root;

  /**
   * @var string
   */
  protected $uri;

  /**
   * Constructor.
   *
   * @param Boot
   *   The default bootstrap object to use when there are
   *   no viable candidates to use (e.g. no selected site)
   */
  public function __construct(Boot $default) {
    $this->defaultBootstrapObject = $default;
  }

  /**
   * Add a bootstrap object to the list of candidates
   *
   * @param Boot|Array
   *   List of boot candidates
   */
  public function add($candidateList) {
    foreach (func_get_args() as $candidate) {
      $this->bootstrapCandidates[] = $candidate;
    }
  }

  /**
   * Return the framework root selected by the user.
   */
  public function getRoot() {
    return $this->root;
  }

  public function setRoot($root) {
    // TODO: Throw if we already bootstrapped a framework?
    $this->root = $root;
  }

  /**
   * Return the framework root selected by the user.
   */
  public function getUri() {
    return $this->uri;
  }

  public function setUri($uri) {
    // TODO: Throw if we already bootstrapped a framework?
    $this->uri = $root;
  }

  /**
   * Return the bootstrap object in use.  This will
   * be the latched bootstrap object if we have started
   * bootstrapping; otherwise, it will be whichever bootstrap
   * object is best for the selected root.
   */
  public function bootstrap() {
    if ($this->bootstrap) {
      return $this->bootstrap;
    }
    return $this->selectBootstrapClass();
  }

  /**
   * Look up the best bootstrap class for the given location
   * from the set of available candidates.
   */
  public function bootstrapObjectForRoot($path) {
    foreach ($this->bootstrapCandidates as $candidate) {
      if ($candidate->valid_root($path)) {
        return $candidate;
      }
    }
    return NULL;
  }

  /**
   * Select the bootstrap class to use.  If this is called multiple
   * times, the bootstrap class returned might change on subsequent
   * calls, if the root directory changes.  Once the bootstrap object
   * starts changing the state of the system, however, it will
   * be 'latched', and further calls to \Drush::bootstrapf()
   * will always return the same object.
   */
  protected function selectBootstrapClass() {
    // Once we have selected a Drupal root, we will reduce our bootstrap
    // candidates down to just the one used to select this site root.
    $bootstrap = $this->bootstrapObjectForRoot($this->root);
    // If we have not found a bootstrap class by this point,
    // then return our default bootstrap object.  The default bootstrap object
    // should pass through all calls without doing anything that
    // changes state in a CMS-specific way.
    if ($bootstrap == NULL) {
      $bootstrap = $this->defaultBootstrapObject;
    }

    return $bootstrap;
  }

  /**
   * Once bootstrapping has started, we stash the bootstrap
   * object being used, and do not allow it to change any
   * longer.
   */
  public function latch($bootstrap) {
    $this->bootstrap = $bootstrap;
  }

}
