<?php

namespace Drush\Symfony;
use Symfony\Component\Console\Output;


Class DrushConsoleOutput extends \Symfony\Component\Console\Output\ConsoleOutput {

    protected function hasStdoutSupport()
    {
        return false;
    }

}
