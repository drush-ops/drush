<?php

namespace Drush\Boot;

trait AutoloaderAwareTrait
{
    protected $loader;

    public function setAutoloader($loader)
    {
        $this->loader = $loader;
    }

    public function autoloader()
    {
        return $this->loader;
    }

    public function hasAutoloader()
    {
        return isset($this->loader);
    }
}
