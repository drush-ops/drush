<?php

namespace Drush\Process;

use Drush\Drush;

/**
 * Drush's wrapper around Symfony Process.
 *
 * - Supports simulated mode. A user enables this by using --simulate option.
 * - Logs all commands. Use -v to see them.
 */
class Process extends ProcessVerbose
{
    private $isSimulated = false;

    /**
     * @inheritDoc
     */
    public function __construct($commandline, $cwd = null, array $env = null, $input = null, $timeout = 60, array $options = null)
    {
        $this->setIsSimulated(Drush::simulate());
        parent::__construct($commandline, $cwd, $env, $input, $timeout, $options);
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
        if ($this->isSimulated()) {
            Drush::logger()->info('Simulating: ' . $this->getCommandLine());
            $cmd = $this->getCommandLine();
            $this->setCommandLine('exit 0');
        }
        $return = parent::start($callback);
        if ($this->isSimulated()) {
            $this->setCommandLine($cmd);
        }
        return $return;
    }

    /**
     * @inheritDoc
     */
//    public function wait(callable $callback = null)
//    {
//        return $this->isSimulated() ? 0 : parent::wait($callback);
//    }


}
