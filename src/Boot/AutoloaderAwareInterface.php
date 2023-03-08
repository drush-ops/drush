<?php

declare(strict_types=1);

namespace Drush\Boot;

interface AutoloaderAwareInterface
{
    public function setAutoloader($loader);

    public function autoloader();

    public function hasAutoloader();
}
