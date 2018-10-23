<?php

namespace Drush\Process;

use Drush\Drush;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * Drush's wrapper around Symfony Process.
 *
 * - Supports simulated mode. A user enables this by using --simulate option.
 * - Logs all commands. Use -v to see them.
 */
class Process extends SymfonyProcess
{
    private $isSimulated = false;

    private $isVerbose = false;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @inheritDoc
     */
    public function __construct($commandline, $cwd = null, array $env = null, $input = null, $timeout = 60, array $options = null)
    {
        parent::__construct($commandline, $cwd, $env, $input, $timeout, $options);
    }

    /**
     * @return bool
     */
    public function isVerbose()
    {
        return $this->isVerbose;
    }

    /**
     * @param bool $isVerbose
     */
    public function setIsVerbose($isVerbose)
    {
        $this->isVerbose = $isVerbose;
    }

    /**
     * @return bool
     */
    public function isSimulated()
    {
        return $this->isSimulated;
    }

    /**
     * @param bool $isSimulated
     */
    public function setIsSimulated($isSimulated)
    {
        $this->isSimulated = $isSimulated;
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
        if ($this->isSimulated()) {
            $this->getLogger()->notice('Simulating: ' . $cmd);
            // Run a command that always succeeds.
            $this->setCommandLine('exit 0');
        } elseif ($this->isVerbose()) {
            $this->getLogger()->info('Executing: ' . $cmd);
        }
        $return = parent::start($callback);
        // Set command back to original value in case anyone asks.
        if ($this->isSimulated()) {
            $this->setCommandLine($cmd);
        }
        return $return;
    }
}
