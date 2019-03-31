<?php

namespace Drush\Preflight;

use Symfony\Component\Console\Output\StreamOutput;

class PreflightLog
{
    protected $debug;

    protected $output;

    public function __construct($output = null)
    {
        $this->output = $output ?: new StreamOutput(fopen('php://stderr', 'w'));
    }
    /**
     * @return bool
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * @param bool $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
        return $this;
    }

    public function log($message)
    {
        if ($this->getDebug()) {
            $this->output->write(' [preflight] ' . $message . "\n");
        }
    }
}
