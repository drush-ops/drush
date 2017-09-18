<?php
namespace Drush\Boot;

interface AutoloaderAwareInterface
{
    public function setAutoloader($loader);

    public function autoloader();

    public function hasAutoloader();
}
