<?php

declare(strict_types=1);

namespace Drush\Boot;

use Psr\Log\LoggerInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteProcess\ProcessManagerAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\AnnotationData;
use Drush\DrupalFinder\DrushDrupalFinder;
use Drush\Config\ConfigAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Robo\Contract\ConfigAwareInterface;

class BootstrapManager implements LoggerAwareInterface, ConfigAwareInterface
{
    use LoggerAwareTrait;
    use ConfigAwareTrait;

    /**
     * @var DrushDrupalFinder
     */
    protected $drupalFinder;

    /**
     * @var Boot[]
     */
    protected $bootstrapCandidates = [];

    /**
     * @var Boot
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
            return DrupalBootLevels::NONE;
        }
        return $this->bootstrap()->getPhase();
    }

    protected function setPhase(int $phase): void
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
    public function add($candidateList): void
    {
        foreach (func_get_args() as $candidate) {
            $this->bootstrapCandidates[] = $candidate;
        }
    }

    public function drupalFinder(): DrushDrupalFinder
    {
        if (!isset($this->drupalFinder)) {
            $this->drupalFinder = new DrushDrupalFinder();
        }
        return $this->drupalFinder;
    }

    public function setDrupalFinder(DrushDrupalFinder $drupalFinder): void
    {
        $this->drupalFinder = $drupalFinder;
    }

    /**
     * Return the framework root selected by the user.
     */
    public function getRoot(): string
    {
        return $this->drupalFinder()->getDrupalRoot();
    }

    /**
     * Return the composer root for the selected Drupal site.
     */
    public function getComposerRoot(): string
    {
        return $this->drupalFinder()->getComposerRoot();
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

    public function setUri($uri): void
    {
        // TODO: Throw if we already bootstrapped a framework?
        // n.b. site-install needs to set the uri.
        $this->bootstrap()->setUri($uri);
    }

    /**
     * Crete the bootstrap object if necessary, then return it.
     */
    public function bootstrap(): Boot
    {
        if (!$this->bootstrap) {
            $this->bootstrap = $this->bootstrapObjectForRoot($this->getRoot());
        }
        return $this->bootstrap;
    }

    /**
     * For use in testing
     */
    public function injectBootstrap(Boot $bootstrap): void
    {
        $bootstrap->setLogger($this->logger());
        $this->bootstrap = $bootstrap;

        // Our bootstrap object is always a DrupalBoot8.
        // TODO: make an API in the Boot interface to call.
        $bootstrap->addDrupalModuleDrushCommands($this);
    }

    /**
     * Look up the best bootstrap class for the given location
     * from the set of available candidates.
     */
    public function bootstrapObjectForRoot($path): Boot
    {
        foreach ($this->bootstrapCandidates as $candidate) {
            if ($candidate->validRoot($path)) {
                return $candidate;
            }
        }
        return new EmptyBoot();
    }

    /**
     * Returns an array that determines what bootstrap phases
     * are necessary to bootstrap the CMS.
     *
     * @param bool $function_names
     *   (optional) If TRUE, return an array of method names index by their
     *   corresponding phase values. Otherwise return an array of phase values.
     *
     *
     * @see \Drush\Boot\Boot::bootstrapPhases()
     */
    public function bootstrapPhases(bool $function_names = false): array
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
     * @param AnnotationData $annotationData
     *   Optional annotation data from the command.
     *
     *   TRUE if the specified bootstrap phase has completed.
     * @see \Drush\Boot\Boot::bootstrapPhases()
     */
    public function doBootstrap(int $phase, $phase_max = false, AnnotationData $annotationData = null): bool
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

        foreach ($phases as $phase_index => $current_phase) {
            $bootstrapped_phase = $this->getPhase();
            if ($phase_index > $phase) {
                break;
            }
            if ($phase_index > $bootstrapped_phase) {
                if ($result = $this->bootstrapValidate($phase_index)) {
                    if (method_exists($bootstrap, $current_phase)) {
                        $this->logger->info('Drush bootstrap phase: {function}()', ['function' => $current_phase]);
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
    public function hasBootstrap(): bool
    {
        return $this->bootstrap != null;
    }

    /**
     * Determine whether a given bootstrap phase has been completed.
     *
     * @param int $phase
     *   The bootstrap phase to test
     *
     *   TRUE if the specified bootstrap phase has completed.
     */
    public function hasBootstrapped(int $phase): bool
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
     *   TRUE if bootstrap is possible, FALSE if the validation failed.
     * @see \Drush\Boot\Boot::bootstrapPhases()
     */
    public function bootstrapValidate(int $phase): bool
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
                    $result_cache[$phase_index] = method_exists($bootstrap, $current_phase) ? $bootstrap->{$current_phase}($this) : true;
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
     *   Optional annotation data from the command.
     *
     *   TRUE if the specified bootstrap phase has completed.
     * @throws \Exception
     *   Thrown when an unknown bootstrap phase is passed in the annotation
     *   data.
     */
    public function bootstrapToPhase(string $bootstrapPhase, AnnotationData $annotationData = null): bool
    {
        $this->logger->info('Starting bootstrap to {phase}', ['phase' => $bootstrapPhase]);
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
     *   Optional annotation data from the command.
     *   TRUE if the specified bootstrap phase has completed.
     */
    public function bootstrapToPhaseIndex(int $max_phase_index, AnnotationData $annotationData = null): bool
    {
        if ($max_phase_index == DRUSH_BOOTSTRAP_MAX) {
            // Try get a max phase.
            $bootstrap_str = $annotationData->get('bootstrap');
            $stop_phase = $this->maxPhaseLimit($bootstrap_str);
            $this->bootstrapMax($stop_phase);
            return true;
        }

        $this->logger->info('Drush bootstrap phase {phase}', ['phase' => $max_phase_index]);
        $phases = $this->bootstrapPhases();
        $result = true;

          // Try to bootstrap to the maximum possible level, without generating errors
        foreach ($phases as $phase_index) {
            if ($phase_index > $max_phase_index) {
                // Stop trying, since we achieved what was specified.
                break;
            }

            $this->logger->info('Try to validate bootstrap phase {phase}', ['phase' => $max_phase_index]);

            if ($this->bootstrapValidate($phase_index)) {
                if ($phase_index > $this->getPhase()) {
                    $this->logger->info('Try to bootstrap at phase {phase}', ['phase' => $max_phase_index]);
                    $result = $this->doBootstrap($phase_index, $max_phase_index, $annotationData);
                }
            } else {
                $this->logger->info('Could not bootstrap at phase {phase}', ['phase' => $max_phase_index]);
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
     * @param AnnotationData $annotationData
     *   Optional annotation data from the command.
     *
     *   The maximum phase to which we bootstrapped.
     */
    public function bootstrapMax($max_phase_index = false, AnnotationData $annotationData = null): int
    {
        // Bootstrap as far as we can without throwing an error, but log for
        // debugging purposes.

        $phases = $this->bootstrapPhases(true);
        if (!$max_phase_index) {
            $max_phase_index = count($phases);
        }

        if ($max_phase_index >= count($phases)) {
            $this->logger->debug('Trying to bootstrap as far as we can');
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
                $this->logger->debug('Bootstrap phase {function}() failed to validate; continuing at {current}()', ['function' => $current_phase, 'current' => $phases[$previous]]);
                break;
            }
        }

        return $this->getPhase();
    }

    /**
     * Allow those with a reference to the BootstrapManager to use its logger
     */
    public function logger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
