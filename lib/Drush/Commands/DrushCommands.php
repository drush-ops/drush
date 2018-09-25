<?php
namespace Drush\Commands;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class DrushCommands implements LoggerAwareInterface
{
    // This is more readable.
    const REQ=InputOption::VALUE_REQUIRED;
    const OPT=InputOption::VALUE_OPTIONAL;

    use LoggerAwareTrait;

    public function __construct()
    {
    }

    /**
     * Returns a logger object.
     *
     * @return LoggerInterface
     */
    protected function logger()
    {
        return $this->logger;
    }

    /**
     * Print the contents of a file.
     *
     * @param string $file
     *   Full path to a file.
     */
    protected function printFile($file)
    {
        drush_print_file($file);
    }
}
