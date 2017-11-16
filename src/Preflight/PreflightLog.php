<?php

namespace Drush\Preflight;

class PreflightLog {

    protected $debug;

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
    }

    public function log($message)
    {
        if ($this->getDebug()) {
            fwrite(STDERR, ' [preflight] ' . $message . "\n");
        }
    }
}
