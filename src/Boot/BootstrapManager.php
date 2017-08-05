<?php

namespace Drush\Boot;

use DrupalFinder\DrupalFinder;
use Drush\Drush;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class BootstrapManager implements LoggerAwareInterface, AutoloaderAwareInterface
{
    use LoggerAwareTrait;
    use AutoloaderAwareTrait;

    /**
     * @var DrupalFinder
     */
    protected $drupalFinder;

    /**
     * @var \Drush\Boot\Boot[]
     */
    protected $bootstrapCandidates = [];

    /**
     * @var \Drush\Boot\Boot
     */
    protected $defaultBootstrapObject;

    /**
     * @var \Drush\Boot\Boot
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
     * @param \Drush\Boot\Boot
     *   The default bootstrap object to use when there are
     *   no viable candidates to use (e.g. no selected site)
     */
    public function __construct(Boot $default)
    {
        $this->defaultBootstrapObject = $default;
        $this->drupalFinder = new DrupalFinder();
    }

    /**
     * Add a bootstrap object to the list of candidates
     *
     * @param \Drush\Boot\Boot|Array
     *   List of boot candidates
     */
    public function add($candidateList)
    {
        foreach (func_get_args() as $candidate) {
            $this->bootstrapCandidates[] = $candidate;
        }
    }

    /**
     * Return the framework root selected by the user.
     */
    public function getRoot()
    {
        return $this->drupalFinder->getDrupalRoot();
    }

    /**
     * Return the composer root for the selected Drupal site.
     */
    public function getComposerRoot()
    {
        return $this->drupalFinder->getComposerRoot();
    }

    public function locateRoot($root, $start_path = null)
    {
        // TODO: Throw if we already bootstrapped a framework?

        if (!isset($root)) {
            $root = drush_cwd();
        }
        if (!$this->drupalFinder->locateRoot($root)) {
            //    echo ' Drush must be executed within a Drupal site.'. PHP_EOL;
            //    exit(1);
        }
    }

    /**
     * Return the framework root selected by the user.
     */
    public function getUri()
    {
        return $this->uri;
    }

    public function setUri($uri)
    {
        // TODO: Throw if we already bootstrapped a framework?
        $this->uri = $root;
    }

    /**
     * Return the bootstrap object in use.  This will
     * be the latched bootstrap object if we have started
     * bootstrapping; otherwise, it will be whichever bootstrap
     * object is best for the selected root.
     *
     * @return \Drush\Boot\Boot
     */
    public function bootstrap()
    {
        if ($this->bootstrap) {
            return $this->bootstrap;
        }
        return $this->selectBootstrapClass();
    }

    /**
     * Look up the best bootstrap class for the given location
     * from the set of available candidates.
     *
     * @return \Drush\Boot\Boot
     */
    public function bootstrapObjectForRoot($path)
    {
        foreach ($this->bootstrapCandidates as $candidate) {
            if ($candidate->validRoot($path)) {
                if ($candidate instanceof AutoloaderAwareInterface) {
                    $candidate->setAutoloader($this->autoloader());
                }
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Select the bootstrap class to use.  If this is called multiple
     * times, the bootstrap class returned might change on subsequent
     * calls, if the root directory changes.  Once the bootstrap object
     * starts changing the state of the system, however, it will
     * be 'latched', and further calls to Drush::bootstrapf()
     * will always return the same object.
     */
    protected function selectBootstrapClass()
    {
        // Once we have selected a Drupal root, we will reduce our bootstrap
        // candidates down to just the one used to select this site root.
        $bootstrap = $this->bootstrapObjectForRoot($this->getRoot());
        // If we have not found a bootstrap class by this point,
        // then return our default bootstrap object.  The default bootstrap object
        // should pass through all calls without doing anything that
        // changes state in a CMS-specific way.
        if ($bootstrap == null) {
            $bootstrap = $this->defaultBootstrapObject;
        }

        return $bootstrap;
    }

    /**
     * Once bootstrapping has started, we stash the bootstrap
     * object being used, and do not allow it to change any
     * longer.
     */
    public function latch($bootstrap)
    {
        $this->bootstrap = $bootstrap;
    }
}
