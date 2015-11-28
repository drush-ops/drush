<?php

namespace Drush\Process;

/**
 * Behaves like a Process, but does not actually run anything.
 *
 * @api
 */
class SimulatedProcess extends Symfony\Component\Process\Process
{

    /**
     * Constructor.
     *
     * @param string             $commandline The command line that would run were this not a simulated Process
     *
     * @api
     */
    public function __construct($commandline, $cwd = null, array $env = null, $stdin = null, $timeout = 60, array $options = array())
    {
        parent::__construct($commandline, $cwd, $env, $stdin, $timeout, $options);
    }

    /**
     * Simulate starting the process.
     */
    public function start($callback = null)
    {
        // TODO: how should we output the debug message?
        // Do we care to display any of the other arguments?
        print("Executing " . $this->getCommandLine() . "\n");
    }

    public function restart($callback = null)
    {
        return $this;
    }

    /**
     * Simulated wait == no wait.
     */
    public function wait($callback = null)
    {
        return 0;
    }

    /**
     * No pid, always return null
     */
    public function getPid()
    {
        return null;
    }

    /**
     * Simulated signal.
     */
    public function signal($signal)
    {
        return $this;
    }

    /**
     * Simulated commands never produce output.
     */
    public function getOutput()
    {
        return '';
    }

    public function getIncrementalOutput()
    {
        return '';
    }

    public function clearOutput()
    {
        return $this;
    }

    public function getErrorOutput()
    {
        return '';
    }

    public function getIncrementalErrorOutput()
    {
        return '';
    }

    public function clearErrorOutput()
    {
        return $this;
    }

    public function getExitCode()
    {
        return 0;
    }

    public function hasBeenSignaled()
    {
        return FALSE;
    }

    public function getTermSignal()
    {
        return 0;
    }

    public function hasBeenStopped()
    {
        return FALSE;
    }

    public function getStopSignal()
    {
        return 0;
    }

    public function isRunning()
    {
        return FALSE;
    }

    public function isStarted()
    {
        return FALSE;
    }

    public function isTerminated()
    {
        $this->updateStatus(false);

        return TRUE;
    }

    public function getStatus()
    {
        return STATUS_TERMINATED;
    }

    public function stop($timeout = 10, $signal = null)
    {
        return 0;
    }

    public function checkTimeout()
    {
    }
}
