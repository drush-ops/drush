<?php

namespace Drush\Boot;

use Consolidation\AnnotatedCommand\AnnotationData;
use DrupalFinder\DrupalFinder;
use Drush\Log\LogLevel;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Drush\Config\ConfigAwareTrait;
use Robo\Contract\ConfigAwareInterface;

class BootstrapManager implements LoggerAwareInterface, AutoloaderAwareInterface, ConfigAwareInterface, ContainerAwareInterface
{
    use LoggerAwareTrait;
    use AutoloaderAwareTrait;
    use ConfigAwareTrait;
    use ContainerAwareTrait;

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
    protected $bootstrap;

    /**
     * @var int
     */
    protected $phase;

    /**
     * @return int
     */
    public function getPhase()
    {
        if (!$this->hasBootstrap()) {
            return DRUSH_BOOTSTRAP_NONE;
        }
        return $this->bootstrap()->getPhase();
    }

    /**
     * @param int $phase
     */
    protected function setPhase($phase)
    {
        if ($this->bootstrap) {
            $this->bootstrap()->setPhase($phase);
        }
    }

    /**
     * Add a bootstrap object to the list of candidates.
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

    public function drupalFinder()
    {
        if (!isset($this->drupalFinder)) {
            $this->drupalFinder = new DrupalFinder();
        }
        return $this->drupalFinder;
    }

    public function setDrupalFinder(DrupalFinder $drupalFinder)
    {
        $this->drupalFinder = $drupalFinder;
    }

    /**
     * Return the framework root selected by the user.
     */
    public function getRoot()
    {
        return $this->drupalFinder()->getDrupalRoot();
    }

    /**
     * Return the composer root for the selected Drupal site.
     */
    public function getComposerRoot()
    {
        return $this->drupalFinder()->getComposerRoot();
    }

    public function locateRoot($root, $start_path = null)
    {
        // TODO: Throw if we already bootstrapped a framework?

        if (!isset($root)) {
            $root = $this->getConfig()->cwd();
        }
        $this->drupalFinder()->locateRoot($root);
    }

    /**
     * Return the framework uri selected by the user.
     */
    public function getUri()
    {
        if (!$this->hasBootstrap()) {
            return false;
        }
        return $this->bootstrap()->getUri();
    }

    /**
     * This method is called by the Application iff the user
     * did not explicitly provide a URI.
     */
    public function selectUri($cwd)
    {
        $uri = $this->bootstrap()->findUri($this->getRoot(), $cwd);
        $this->setUri($uri);
        return $uri;
    }

    public function setUri($uri)
    {
        // TODO: Throw if we already bootstrapped a framework?
        // n.b. site-install needs to set the uri.
        $this->bootstrap()->setUri($uri);
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
        if (!$this->bootstrap) {
            $this->bootstrap = $this->selectBootstrapClass();
        }
        return $this->bootstrap;
    }

    /**
     * For use in testing
     */
    public function injectBootstrap($bootstrap)
    {
        $this->inflect($bootstrap);
        $this->bootstrap = $bootstrap;

        // Our bootstrap object is always a DrupalBoot8.
        // TODO: make an API in the Boot interface to call.
        $bootstrap->addDrupalModuleDrushCommands($this);
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
                // This is not necessary when the autoloader is inflected
                // TODO: The autoloader is inflected in the symfony dispatch, but not the traditional Drush dispatcher
                if ($candidate instanceof AutoloaderAwareInterface) {
                    $candidate->setAutoloader($this->autoloader());
                }
                return $candidate;
            }
        }
        return new EmptyBoot();
    }

    /**
     * Select the bootstrap class to use.  If this is called multiple
     * times, the bootstrap class returned might change on subsequent
     * calls, if the root directory changes.  Once the bootstrap object
     * starts changing the state of the system, however, it will
     * be 'latched', and further calls to Drush::bootstrap()
     * will always return the same object.
     */
    protected function selectBootstrapClass()
    {
        // Once we have selected a Drupal root, we will reduce our bootstrap
        // candidates down to just the one used to select this site root.
        return $this->bootstrapObjectForRoot($this->getRoot());
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
        $result = [];

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
     * @param int|bool $phase_max
     *   (optional) The maximum level to boot to. This does not have a use in this
     *   function itself but can be useful for other code called from within this
     *   function, to know if e.g. a caller is in the process of booting to the
     *   specified level. If specified, it should never be lower than $phase.
     * @param \Consolidation\AnnotatedCommand\AnnotationData $annotationData
     *   Optional annotation data from the command.
     *
     * @return bool
     *   TRUE if the specified bootstrap phase has completed.
     *
     * @see \Drush\Boot\Boot::bootstrapPhases()
     */
    public function doBootstrap($phase, $phase_max = false, AnnotationData $annotationData = null)
    {
        $bootstrap = $this->bootstrap();
        $phases = $this->bootstrapPhases(true);
        $result = true;

        // If the requested phase does not exist in the list of available
        // phases, it means that the command requires bootstrap to a certain
        // level, but no site root could be found.
        if (!isset($phases[$phase])) {
            throw new \Exception(dt("We could not find an applicable site for that command."));
        }

        // Once we start bootstrapping past the DRUSH_BOOTSTRAP_DRUSH phase, we
        // will latch the bootstrap object, and prevent it from changing.
        if ($phase > DRUSH_BOOTSTRAP_DRUSH) {
            $this->latch($bootstrap);
        }

        foreach ($phases as $phase_index => $current_phase) {
            $bootstrapped_phase = $this->getPhase();
            if ($phase_index > $phase) {
                break;
            }
            if ($phase_index > $bootstrapped_phase) {
                if ($result = $this->bootstrapValidate($phase_index)) {
                    if (method_exists($bootstrap, $current_phase)) {
                        $this->logger->log(LogLevel::BOOTSTRAP, 'Drush bootstrap phase: {function}()', ['function' => $current_phase]);
                        $bootstrap->{$current_phase}($this, $annotationData);
                    }
                    $bootstrap->setPhase($phase_index);
                }
            }
        }
        return true;
    }

    /**
     * hasBootstrap determines whether the manager has a bootstrap object yet.
     */
    public function hasBootstrap()
    {
        return $this->bootstrap != null;
    }

    /**
     * Determine whether a given bootstrap phase has been completed.
     *
     * @param int $phase
     *   The bootstrap phase to test
     *
     * @return bool
     *   TRUE if the specified bootstrap phase has completed.
     */
    public function hasBootstrapped($phase)
    {
        return $this->getPhase() >= $phase;
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
        static $result_cache = [];

        $validated_phase = -1;
        foreach ($phases as $phase_index => $current_phase) {
            if (!array_key_exists($phase_index, $result_cache)) {
                if ($phase_index > $phase) {
                    break;
                }
                if ($phase_index > $validated_phase) {
                    $current_phase .= 'Validate';
                    if (method_exists($bootstrap, $current_phase)) {
                        $result_cache[$phase_index] = $bootstrap->{$current_phase}($this);
                    } else {
                        $result_cache[$phase_index] = true;
                    }
                    $validated_phase = $phase_index;
                }
            }
        }
        return $result_cache[$phase];
    }

    /**
     * Bootstrap to the specified phase.
     *
     * @param string $bootstrapPhase
     *   Name of phase to bootstrap to. Will be converted to appropriate index.
     * @param \Consolidation\AnnotatedCommand\AnnotationData $annotationData
     *   Optional annotation data from the command.
     *
     * @return bool
     *   TRUE if the specified bootstrap phase has completed.
     *
     * @throws \Exception
     *   Thrown when an unknown bootstrap phase is passed in the annotation
     *   data.
     */
    public function bootstrapToPhase($bootstrapPhase, AnnotationData $annotationData = null)
    {
        $this->logger->log(LogLevel::BOOTSTRAP, 'Starting bootstrap to {phase}', ['phase' => $bootstrapPhase]);
        $phase = $this->bootstrap()->lookUpPhaseIndex($bootstrapPhase);
        if (!isset($phase)) {
            throw new \Exception(dt('Bootstrap phase !phase unknown.', ['!phase' => $bootstrapPhase]));
        }
        // Do not attempt to bootstrap to a phase that is unknown to the selected bootstrap object.
        $phases = $this->bootstrapPhases();
        if (!array_key_exists($phase, $phases) && ($phase >= 0)) {
            return false;
        }
        return $this->bootstrapToPhaseIndex($phase, $annotationData);
    }

    protected function maxPhaseLimit($bootstrap_str)
    {
        $bootstrap_words = explode(' ', $bootstrap_str);
        array_shift($bootstrap_words);
        if (empty($bootstrap_words)) {
            return null;
        }
        $stop_phase_name = array_shift($bootstrap_words);
        return $this->bootstrap()->lookUpPhaseIndex($stop_phase_name);
    }

    /**
     * Bootstrap to the specified phase.
     *
     * @param int $max_phase_index
     *   Only attempt bootstrap to the specified level.
     * @param \Consolidation\AnnotatedCommand\AnnotationData $annotationData
     *   Optional annotation data from the command.
     *
     * @return bool
     *   TRUE if the specified bootstrap phase has completed.
     */
    public function bootstrapToPhaseIndex($max_phase_index, AnnotationData $annotationData = null)
    {
        if ($max_phase_index == DRUSH_BOOTSTRAP_MAX) {
            // Try get a max phase.
            $bootstrap_str = $annotationData->get('bootstrap');
            $stop_phase = $this->maxPhaseLimit($bootstrap_str);
            $this->bootstrapMax($stop_phase);
            return true;
        }

        $this->logger->log(LogLevel::BOOTSTRAP, 'Drush bootstrap phase {phase}', ['phase' => $max_phase_index]);
        $phases = $this->bootstrapPhases();
        $result = true;

          // Try to bootstrap to the maximum possible level, without generating errors
        foreach ($phases as $phase_index) {
            if ($phase_index > $max_phase_index) {
                // Stop trying, since we achieved what was specified.
                break;
            }

            $this->logger->log(LogLevel::BOOTSTRAP, 'Try to validate bootstrap phase {phase}', ['phase' => $max_phase_index]);

            if ($this->bootstrapValidate($phase_index)) {
                if ($phase_index > $this->getPhase()) {
                    $this->logger->log(LogLevel::BOOTSTRAP, 'Try to bootstrap at phase {phase}', ['phase' => $max_phase_index]);
                    $result = $this->doBootstrap($phase_index, $max_phase_index, $annotationData);
                }
            } else {
                $this->logger->log(LogLevel::BOOTSTRAP, 'Could not bootstrap at phase {phase}', ['phase' => $max_phase_index]);
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
     * @param \Consolidation\AnnotatedCommand\AnnotationData $annotationData
     *   Optional annotation data from the command.
     *
     * @return int
     *   The maximum phase to which we bootstrapped.
     */
    public function bootstrapMax($max_phase_index = false, AnnotationData $annotationData = null)
    {
        // Bootstrap as far as we can without throwing an error, but log for
        // debugging purposes.

        $phases = $this->bootstrapPhases(true);
        if (!$max_phase_index) {
            $max_phase_index = count($phases);
        }

        if ($max_phase_index >= count($phases)) {
            $this->logger->log(LogLevel::DEBUG, 'Trying to bootstrap as far as we can');
        }

        // Try to bootstrap to the maximum possible level, without generating errors.
        foreach ($phases as $phase_index => $current_phase) {
            if ($phase_index > $max_phase_index) {
                // Stop trying, since we achieved what was specified.
                break;
            }

            if ($this->bootstrapValidate($phase_index)) {
                if ($phase_index > $this->getPhase()) {
                    $this->doBootstrap($phase_index, $max_phase_index, $annotationData);
                }
            } else {
                // $this->bootstrapValidate() only logs successful validations. For us,
                // knowing what failed can also be important.
                $previous = $this->getPhase();
                $this->logger->log(LogLevel::DEBUG, 'Bootstrap phase {function}() failed to validate; continuing at {current}()', ['function' => $current_phase, 'current' => $phases[$previous]]);
                break;
            }
        }

        return $this->getPhase();
    }

    /**
     * Allow those with an instance to us to the BootstrapManager to use its logger
     */
    public function logger()
    {
        return $this->logger;
    }

    public function inflect($object)
    {
        // See \Drush\Runtime\DependencyInjection::addDrushServices and
        // \Robo\Robo\addInflectors
        $container = $this->getContainer();
        if ($object instanceof \Robo\Contract\ConfigAwareInterface) {
            $object->setConfig($container->get('config'));
        }
        if ($object instanceof \Psr\Log\LoggerAwareInterface) {
            $object->setLogger($container->get('logger'));
        }
        if ($object instanceof \League\Container\ContainerAwareInterface) {
            $object->setContainer($container->get('container'));
        }
        if ($object instanceof \Symfony\Component\Console\Input\InputAwareInterface) {
            $object->setInput($container->get('input'));
        }
        if ($object instanceof \Robo\Contract\OutputAwareInterface) {
            $object->setOutput($container->get('output'));
        }
        if ($object instanceof \Robo\Contract\ProgressIndicatorAwareInterface) {
            $object->setProgressIndicator($container->get('progressIndicator'));
        }
        if ($object instanceof \Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface) {
            $object->setHookManager($container->get('hookManager'));
        }
        if ($object instanceof \Robo\Contract\VerbosityThresholdInterface) {
            $object->setOutputAdapter($container->get('outputAdapter'));
        }
        if ($object instanceof \Consolidation\SiteAlias\SiteAliasManagerAwareInterface) {
            $object->setSiteAliasManager($container->get('site.alias.manager'));
        }
        if ($object instanceof \Consolidation\SiteProcess\ProcessManagerAwareInterface) {
            $object->setProcessManager($container->get('process.manager'));
        }
        if ($object instanceof \Consolidation\AnnotatedCommand\Input\StdinAwareInterface) {
            $object->setStdinHandler($container->get('stdinHandler'));
        }
    }
}
