<?php

namespace Drush\Process;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * A wrapper around Symfony Process.
 *
 * - Supports simulated mode. Typically enabled via a --simulate option.
 * - Supports verbose mode - logs all runs.
 */
class Process extends SymfonyProcess
{
    private $simulated = false;

    private $verbose = false;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @return bool
     */
    public function getVerbose()
    {
        return $this->verbose;
    }

    /**
     * @param bool $verbose
     */
    public function setVerbose($verbose)
    {
        $this->verbose = $verbose;
    }

    /**
     * @return bool
     */
    public function getSimulated()
    {
        return $this->simulated;
    }

    /**
     * @param bool $simulated
     */
    public function setSimulated($simulated)
    {
        $this->simulated = $simulated;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function start(callable $callback = null)
    {
        $cmd = $this->getCommandLine();
        if ($this->getSimulated()) {
            $this->getLogger()->notice('Simulating: ' . $cmd);
            // Run a command that always succeeds.
            $this->setCommandLine('exit 0');
        } elseif ($this->getVerbose()) {
            $this->getLogger()->info('Executing: ' . $cmd);
        }
        $return = parent::start($callback);
        // Set command back to original value in case anyone asks.
        if ($this->getSimulated()) {
            $this->setCommandLine($cmd);
        }
        return $return;
    }
}
