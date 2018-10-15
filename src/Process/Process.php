<?php

namespace Drush\Process;

use Drush\Drush;
use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * Drush's wrapper around Symfony Process.
 *
 * - Supports simulated mode. A user enables this by using --simulate option.
 * - Logs all commands. Use -v to see them.
 */
class Process extends SymfonyProcess
{
    private $isSimulated;

    private $isVerbose;

    /**
     * @inheritDoc
     */
    public function __construct($commandline, $cwd = null, array $env = null, $input = null, $timeout = 60, array $options = null)
    {
        $this->setIsSimulated(Drush::simulate());
        $this->setIsVerbose(Drush::verbose());
        parent::__construct($commandline, $cwd, $env, $input, $timeout, $options);
    }

    /**
     * @return bool
     */
    public function IsVerbose()
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
     * @inheritDoc
     */
    public function start(callable $callback = null)
    {
        $cmd = $this->getCommandLine();
        if ($this->isSimulated()) {
            Drush::logger()->notice('Simulating: ' . $cmd);
            // Run a command that always succeeds.
            $this->setCommandLine('exit 0');
        } elseif ($this->IsVerbose()) {
            Drush::logger()->info('Executing: ' . $cmd);
        }
        $return = parent::start($callback);
        // Set command back to original value in case anyone asks.
        if ($this->isSimulated()) {
            $this->setCommandLine($cmd);
        }
        return $return;
    }
}
