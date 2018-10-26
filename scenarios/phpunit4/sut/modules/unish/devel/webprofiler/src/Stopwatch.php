<?php

namespace Drupal\webprofiler {

  /**
   * Class Stopwatch
   */
  class Stopwatch extends \Symfony\Component\Stopwatch\Stopwatch {

  }

}

namespace Symfony\Component\Stopwatch {

  /**
   * Class Stopwatch
   */
  class Stopwatch {
    /**
     * @var Section[]
     */
    private $sections;

    /**
     * @var array
     */
    private $activeSections;

    /**
     *
     */
    public function __construct() {
      $this->sections = $this->activeSections = ['__root__' => new Section('__root__')];
    }

    /**
     * Creates a new section or re-opens an existing section.
     *
     * @param string|null $id The id of the session to re-open, null to create a new one
     *
     * @throws \LogicException When the section to re-open is not reachable
     */
    public function openSection($id = NULL) {
      $current = end($this->activeSections);

      if (NULL !== $id && NULL === $current->get($id)) {
        throw new \LogicException(sprintf('The section "%s" has been started at an other level and can not be opened.', $id));
      }

      $this->start('__section__.child', 'section');
      $this->activeSections[] = $current->open($id);
      $this->start('__section__');
    }

    /**
     * Stops the last started section.
     *
     * The id parameter is used to retrieve the events from this section.
     *
     * @see getSectionEvents
     *
     * @param string $id The identifier of the section
     *
     * @throws \LogicException When there's no started section to be stopped
     */
    public function stopSection($id) {
      $this->stop('__section__');

      if (1 == count($this->activeSections)) {
        throw new \LogicException('There is no started section to stop.');
      }

      $this->sections[$id] = array_pop($this->activeSections)->setId($id);
      $this->stop('__section__.child');
    }

    /**
     * Starts an event.
     *
     * @param string $name The event name
     * @param string $category The event category
     *
     * @return StopwatchEvent A StopwatchEvent instance
     */
    public function start($name, $category = NULL) {
      return end($this->activeSections)->startEvent($name, $category);
    }

    /**
     * Checks if the event was started
     *
     * @param string $name The event name
     *
     * @return bool
     */
    public function isStarted($name) {
      return end($this->activeSections)->isEventStarted($name);
    }

    /**
     * Stops an event.
     *
     * @param string $name The event name
     *
     * @return StopwatchEvent A StopwatchEvent instance
     */
    public function stop($name) {
      return end($this->activeSections)->stopEvent($name);
    }

    /**
     * Stops then restarts an event.
     *
     * @param string $name The event name
     *
     * @return StopwatchEvent A StopwatchEvent instance
     */
    public function lap($name) {
      return end($this->activeSections)->stopEvent($name)->start();
    }

    /**
     * Gets all events for a given section.
     *
     * @param string $id A section identifier
     *
     * @return StopwatchEvent[] An array of StopwatchEvent instances
     */
    public function getSectionEvents($id) {
      return isset($this->sections[$id]) ? $this->sections[$id]->getEvents() : [];
    }
  }


  /**
   * @internal This class is for internal usage only
   *
   * @author Fabien Potencier <fabien@symfony.com>
   */
  class Section {
    /**
     * @var StopwatchEvent[]
     */
    private $events = [];

    /**
     * @var null|float
     */
    private $origin;

    /**
     * @var string
     */
    private $id;

    /**
     * @var Section[]
     */
    private $children = [];

    /**
     * Constructor.
     *
     * @param float|null $origin Set the origin of the events in this section, use null to set their origin to their start time
     */
    public function __construct($origin = NULL) {
      $this->origin = is_numeric($origin) ? $origin : NULL;
    }

    /**
     * Returns the child section.
     *
     * @param string $id The child section identifier
     *
     * @return Section|null The child section or null when none found
     */
    public function get($id) {
      foreach ($this->children as $child) {
        if ($id === $child->getId()) {
          return $child;
        }
      }

      return NULL;
    }

    /**
     * Creates or re-opens a child section.
     *
     * @param string|null $id null to create a new section, the identifier to re-open an existing one.
     *
     * @return Section A child section
     */
    public function open($id) {
      if (NULL === $session = $this->get($id)) {
        $session = $this->children[] = new self(microtime(TRUE) * 1000);
      }

      return $session;
    }

    /**
     * @return string The identifier of the section
     */
    public function getId() {
      return $this->id;
    }

    /**
     * Sets the session identifier.
     *
     * @param string $id The session identifier
     *
     * @return Section The current section
     */
    public function setId($id) {
      $this->id = $id;

      return $this;
    }

    /**
     * Starts an event.
     *
     * @param string $name The event name
     * @param string $category The event category
     *
     * @return StopwatchEvent The event
     */
    public function startEvent($name, $category) {
      if (!isset($this->events[$name])) {
        $this->events[$name] = new StopwatchEvent($this->origin ?: microtime(TRUE) * 1000, $category);
      }

      return $this->events[$name]->start();
    }

    /**
     * Checks if the event was started
     *
     * @param string $name The event name
     *
     * @return bool
     */
    public function isEventStarted($name) {
      return isset($this->events[$name]) && $this->events[$name]->isStarted();
    }

    /**
     * Stops an event.
     *
     * @param string $name The event name
     *
     * @return StopwatchEvent The event
     *
     * @throws \LogicException When the event has not been started
     */
    public function stopEvent($name) {
      if (!isset($this->events[$name])) {
        throw new \LogicException(sprintf('Event "%s" is not started.', $name));
      }

      return $this->events[$name]->stop();
    }

    /**
     * Stops then restarts an event.
     *
     * @param string $name The event name
     *
     * @return StopwatchEvent The event
     *
     * @throws \LogicException When the event has not been started
     */
    public function lap($name) {
      return $this->stopEvent($name)->start();
    }

    /**
     * Returns the events from this section.
     *
     * @return StopwatchEvent[] An array of StopwatchEvent instances
     */
    public function getEvents() {
      return $this->events;
    }
  }

  /**
   * Class StopwatchEvent
   */
  class StopwatchEvent {
    /**
     * @var StopwatchPeriod[]
     */
    private $periods = [];

    /**
     * @var float
     */
    private $origin;

    /**
     * @var string
     */
    private $category;

    /**
     * @var float[]
     */
    private $started = [];

    /**
     * Constructor.
     *
     * @param float $origin The origin time in milliseconds
     * @param string|null $category The event category or null to use the default
     *
     * @throws \InvalidArgumentException When the raw time is not valid
     */
    public function __construct($origin, $category = NULL) {
      $this->origin = $this->formatTime($origin);
      $this->category = is_string($category) ? $category : 'default';
    }

    /**
     * Gets the category.
     *
     * @return string The category
     */
    public function getCategory() {
      return $this->category;
    }

    /**
     * Gets the origin.
     *
     * @return float The origin in milliseconds
     */
    public function getOrigin() {
      return $this->origin;
    }

    /**
     * Starts a new event period.
     *
     * @return StopwatchEvent The event
     */
    public function start() {
      $this->started[] = $this->getNow();

      return $this;
    }

    /**
     * Stops the last started event period.
     *
     * @throws \LogicException When start wasn't called before stopping
     *
     * @return StopwatchEvent The event
     *
     * @throws \LogicException When stop() is called without a matching call to start()
     */
    public function stop() {
      if (!count($this->started)) {
        throw new \LogicException('stop() called but start() has not been called before.');
      }

      $this->periods[] = new StopwatchPeriod(array_pop($this->started), $this->getNow());

      return $this;
    }

    /**
     * Checks if the event was started
     *
     * @return bool
     */
    public function isStarted() {
      return !empty($this->started);
    }

    /**
     * Stops the current period and then starts a new one.
     *
     * @return StopwatchEvent The event
     */
    public function lap() {
      return $this->stop()->start();
    }

    /**
     * Stops all non already stopped periods.
     */
    public function ensureStopped() {
      while (count($this->started)) {
        $this->stop();
      }
    }

    /**
     * Gets all event periods.
     *
     * @return StopwatchPeriod[] An array of StopwatchPeriod instances
     */
    public function getPeriods() {
      return $this->periods;
    }

    /**
     * Gets the relative time of the start of the first period.
     *
     * @return integer The time (in milliseconds)
     */
    public function getStartTime() {
      return isset($this->periods[0]) ? $this->periods[0]->getStartTime() : 0;
    }

    /**
     * Gets the relative time of the end of the last period.
     *
     * @return integer The time (in milliseconds)
     */
    public function getEndTime() {
      return ($count = count($this->periods)) ? $this->periods[$count - 1]->getEndTime() : 0;
    }

    /**
     * Gets the duration of the events (including all periods).
     *
     * @return integer The duration (in milliseconds)
     */
    public function getDuration() {
      $total = 0;
      foreach ($this->periods as $period) {
        $total += $period->getDuration();
      }

      return $total;
    }

    /**
     * Gets the max memory usage of all periods.
     *
     * @return integer The memory usage (in bytes)
     */
    public function getMemory() {
      $memory = 0;
      foreach ($this->periods as $period) {
        if ($period->getMemory() > $memory) {
          $memory = $period->getMemory();
        }
      }

      return $memory;
    }

    /**
     * Return the current time relative to origin.
     *
     * @return float Time in ms
     */
    protected function getNow() {
      return $this->formatTime(microtime(TRUE) * 1000 - $this->origin);
    }

    /**
     * Formats a time.
     *
     * @param integer|float $time A raw time
     *
     * @return float The formatted time
     *
     * @throws \InvalidArgumentException When the raw time is not valid
     */
    private function formatTime($time) {
      if (!is_numeric($time)) {
        throw new \InvalidArgumentException('The time must be a numerical value');
      }

      return round($time, 1);
    }
  }

  /**
   * Class StopwatchPeriod
   */
  class StopwatchPeriod {
    private $start;
    private $end;
    private $memory;

    /**
     * Constructor.
     *
     * @param integer $start The relative time of the start of the period (in milliseconds)
     * @param integer $end The relative time of the end of the period (in milliseconds)
     */
    public function __construct($start, $end) {
      $this->start = (integer) $start;
      $this->end = (integer) $end;
      $this->memory = memory_get_usage(TRUE);
    }

    /**
     * Gets the relative time of the start of the period.
     *
     * @return integer The time (in milliseconds)
     */
    public function getStartTime() {
      return $this->start;
    }

    /**
     * Gets the relative time of the end of the period.
     *
     * @return integer The time (in milliseconds)
     */
    public function getEndTime() {
      return $this->end;
    }

    /**
     * Gets the time spent in this period.
     *
     * @return integer The period duration (in milliseconds)
     */
    public function getDuration() {
      return $this->end - $this->start;
    }

    /**
     * Gets the memory usage.
     *
     * @return integer The memory usage (in bytes)
     */
    public function getMemory() {
      return $this->memory;
    }
  }

}
