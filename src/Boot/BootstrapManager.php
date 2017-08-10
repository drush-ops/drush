<?php

namespace Drush\Boot;

use DrupalFinder\DrupalFinder;
use Drush\Drush;
use Drush\Log\LogLevel;
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

    /**
     * Returns an array that determines what bootstrap phases
     * are necessary to bootstrap the CMS.
     *
     * @param bool $function_names
     *   (optional) If TRUE, return an array of method names index by their
     *   corresponding phase values. Otherwise return an array of phase values.
     *
     * @return array
     *
     * @see \Drush\Boot\Boot::bootstrapPhases()
     */
    public function bootstrapPhases($function_names = false)
    {
        $result = array();

        if ($bootstrap = $this->bootstrap()) {
            $result = $bootstrap->bootstrapPhases();
            if (!$function_names) {
                $result = array_keys($result);
            }
        }
        return $result;
    }

    /**
     * Bootstrap Drush to the desired phase.
     *
     * This function will sequentially bootstrap each
     * lower phase up to the phase that has been requested.
     *
     * @param int $phase
     *   The bootstrap phase to bootstrap to.
     * @param int $phase_max
     *   (optional) The maximum level to boot to. This does not have a use in this
     *   function itself but can be useful for other code called from within this
     *   function, to know if e.g. a caller is in the process of booting to the
     *   specified level. If specified, it should never be lower than $phase.
     *
     * @return bool
     *   TRUE if the specified bootstrap phase has completed.
     *
     * @see \Drush\Boot\Boot::bootstrapPhases()
     */
    public function doBootstrap($phase, $phase_max = false)
    {
        $bootstrap = $this->bootstrap();
        $phases = $this->bootstrapPhases(true);
        $result = true;

        // If the requested phase does not exist in the list of available
        // phases, it means that the command requires bootstrap to a certain
        // level, but no site root could be found.
        if (!isset($phases[$phase])) {
            $result = drush_bootstrap_error('DRUSH_NO_SITE', dt("We could not find an applicable site for that command."));
        }

          // Once we start bootstrapping past the DRUSH_BOOTSTRAP_DRUSH phase, we
          // will latch the bootstrap object, and prevent it from changing.
        if ($phase > DRUSH_BOOTSTRAP_DRUSH) {
            $this->latch($bootstrap);
        }

        drush_set_context('DRUSH_BOOTSTRAPPING', true);
        foreach ($phases as $phase_index => $current_phase) {
            $bootstrapped_phase = drush_get_context('DRUSH_BOOTSTRAP_PHASE', -1);
            if ($phase_index > $phase) {
                break;
            }
            if ($phase_index > $bootstrapped_phase) {
                if ($result = $this->bootstrapValidate($phase_index)) {
                    if (method_exists($bootstrap, $current_phase) && !drush_get_error()) {
                        drush_log(dt("Drush bootstrap phase : !function()", array('!function' => $current_phase)), LogLevel::BOOTSTRAP);
                        $bootstrap->{$current_phase}();

                        // Reset commandfile cache and find any new command files that are available during this bootstrap phase.
                        drush_get_commands(true);
                        _drush_find_commandfiles($phase_index, $phase_max);
                    }
                    drush_set_context('DRUSH_BOOTSTRAP_PHASE', $phase_index);
                }
            }
        }
        drush_set_context('DRUSH_BOOTSTRAPPING', false);
        if (!$result || drush_get_error()) {
            $errors = drush_get_context('DRUSH_BOOTSTRAP_ERRORS', array());
            foreach ($errors as $code => $message) {
                drush_set_error($code, $message);
            }
        }
        return !drush_get_error();
    }

    /**
     * Determine whether a given bootstrap phase has been completed
     *
     * This function name has a typo which makes me laugh so we choose not to
     * fix it. Take a deep breath, and smile. See
     * http://en.wikipedia.org/wiki/HTTP_referer
     *
     *
     * @param int $phase
     *   The bootstrap phase to test
     *
     * @return bool
     *   TRUE if the specified bootstrap phase has completed.
     */
    public function hasBootstrapped($phase)
    {
        $phase_index = drush_get_context('DRUSH_BOOTSTRAP_PHASE');

        return isset($phase_index) && ($phase_index >= $phase);
    }

    /**
     * Validate whether a bootstrap phase can be reached.
     *
     * This function will validate the settings that will be used
     * during the actual bootstrap process, and allow commands to
     * progressively bootstrap to the highest level that can be reached.
     *
     * This function will only run the validation function once, and
     * store the result from that execution in a local static. This avoids
     * validating phases multiple times.
     *
     * @param int $phase
     *   The bootstrap phase to validate to.
     *
     * @return bool
     *   TRUE if bootstrap is possible, FALSE if the validation failed.
     *
     * @see \Drush\Boot\Boot::bootstrapPhases()
     */
    public function bootstrapValidate($phase)
    {
        $bootstrap = $this->bootstrap();
        $phases = $this->bootstrapPhases(true);
        static $result_cache = array();

        if (!array_key_exists($phase, $result_cache)) {
            drush_set_context('DRUSH_BOOTSTRAP_ERRORS', array());
            drush_set_context('DRUSH_BOOTSTRAP_VALUES', array());

            foreach ($phases as $phase_index => $current_phase) {
                $validated_phase = drush_get_context('DRUSH_BOOTSTRAP_VALIDATION_PHASE', -1);
                if ($phase_index > $phase) {
                    break;
                }
                if ($phase_index > $validated_phase) {
                    $current_phase .= 'Validate';
                    if (method_exists($bootstrap, $current_phase)) {
                        $result_cache[$phase_index] = $bootstrap->{$current_phase}();
                    } else {
                        $result_cache[$phase_index] = true;
                    }
                    drush_set_context('DRUSH_BOOTSTRAP_VALIDATION_PHASE', $phase_index);
                }
            }
        }
        return $result_cache[$phase];
    }

    /**
     * Bootstrap to the specified phase.
     *
     * @param int $max_phase_index
     *   Only attempt bootstrap to the specified level.
     *
     * @return bool
     *   TRUE if the specified bootstrap phase has completed.
     */
    public function bootstrapToPhase($max_phase_index)
    {
        if ($max_phase_index == DRUSH_BOOTSTRAP_MAX) {
            // Bootstrap as far as we can without throwing an error, but log for
            // debugging purposes.
            drush_log(dt("Trying to bootstrap as far as we can."), 'debug');
            $this->bootstrapMax();
            return true;
        }

        drush_log(dt("Bootstrap to phase !phase.", array('!phase' => $max_phase_index)), LogLevel::BOOTSTRAP);
        $phases = $this->bootstrapPhases();
        $result = true;

          // Try to bootstrap to the maximum possible level, without generating errors
        foreach ($phases as $phase_index) {
            if ($phase_index > $max_phase_index) {
                // Stop trying, since we achieved what was specified.
                break;
            }

            if ($this->bootstrapValidate($phase_index)) {
                if ($phase_index > drush_get_context('DRUSH_BOOTSTRAP_PHASE', DRUSH_BOOTSTRAP_NONE)) {
                    $result = $this->doBootstrap($phase_index, $max_phase_index);
                }
            } else {
                $result = false;
                break;
            }
        }

        return $result;
    }

    /**
     * Bootstrap to the highest level possible, without triggering any errors.
     *
     * @param int $max_phase_index
     *   (optional) Only attempt bootstrap to the specified level.
     *
     * @return int
     *   The maximum phase to which we bootstrapped.
     */
    public function bootstrapMax($max_phase_index = false)
    {
        $phases = $this->bootstrapPhases(true);
        if (!$max_phase_index) {
            $max_phase_index = count($phases);
        }

          // Try to bootstrap to the maximum possible level, without generating errors.
        foreach ($phases as $phase_index => $current_phase) {
            if ($phase_index > $max_phase_index) {
                // Stop trying, since we achieved what was specified.
                break;
            }

            if ($this->bootstrapValidate($phase_index)) {
                if ($phase_index > drush_get_context('DRUSH_BOOTSTRAP_PHASE')) {
                    $this->doBootstrap($phase_index, $max_phase_index);
                }
            } else {
                // $this->bootstrapValidate() only logs successful validations. For us,
                // knowing what failed can also be important.
                $previous = drush_get_context('DRUSH_BOOTSTRAP_PHASE');
                drush_log(dt("Bootstrap phase !function() failed to validate; continuing at !current().", array('!function' => $current_phase, '!current' => $phases[$previous])), 'debug');
                break;
            }
        }

        return drush_get_context('DRUSH_BOOTSTRAP_PHASE');
    }
}
