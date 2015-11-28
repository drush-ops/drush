<?php

namespace Drush\Process;

class ProcessBuilder extends Symfony\Component\Process\ProcessBuilder {

  private $simulated = FALSE;

  public function __construct(array $arguments = array())
  {
    parent::__construct($arguments);

    $this->simulated = (getenv("DRUSH_SIMULATED") != NULL);
  }

  /**
   * Sets 'simulated' mode.
   *
   * Common use case: setSimultate(FALSE) when building a
   * command that does not change any state.
   *
   * @param boolean     $simulate  Whether or not to set simulated mode.
   *
   * @return ProcessBuilder
   */
  public function setSimulated($simulate)
  {
    $this->simulated = $simulate;

    return $this;
  }

  /**
   * Get 'simulated' mode.
   *
   * @return boolean
   */
  public function getSimulated()
  {
    return $this->simulated;
  }

  /**
   * Creates a Process instance and returns it.
   *
   * @return Process
   *
   * @throws LogicException In case no arguments have been provided
   */
  public function getProcess()
  {
    if ($this->simulated) {
      return new SimulatedProcess($this->getCommandLine());
    }
    else {
      return parent::getProcess();
    }
  }
}
