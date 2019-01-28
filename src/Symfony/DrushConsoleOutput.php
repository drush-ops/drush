<?php

namespace Drush\Symfony;

use Symfony\Component\Console\Output;

class DrushConsoleOutput extends \Symfony\Component\Console\Output\ConsoleOutput
{

    protected function hasStdoutSupport()
    {
        if (PHP_SAPI == 'fpm-fcgi') {
            return false;
        } else {
            return parent::hasStdoutSupport();
        }
    }
}
