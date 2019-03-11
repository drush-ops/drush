<?php
namespace Drush\Commands;

use Drush\Command\DrushInputAdapter;
use Drush\Command\DrushOutputAdapter;
use Drush\Drush;
use Drush\SiteAlias\ProcessManager;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * DrushCommands provides access to configuration, IO and the logger,
 * and also provides a "printFile" convenience method for displaying
 * the contents of a file with a pager.
 *
 * Drush extensions that use ONLY the facilities provided by this class,
 * plus any APIs provided by Drupal core and the module they are bundled
 * with should work with both Drush 8 and Drush 9.
 */
abstract class DrushCommands implements LoggerAwareInterface
{
    // This is more readable.
    const REQ=InputOption::VALUE_REQUIRED;
    const OPT=InputOption::VALUE_OPTIONAL;

    use LoggerAwareTrait;

    protected $io;

    public function __construct()
    {
    }

    /**
     * Return an object that has the same signature as a Consolidation\Config\ConfigInterface
     */
    protected function getConfig()
    {
        return Drush::config();
    }

    /**
     * @return ProcessManager
     */
    public function processManager()
    {
        return Drush::processManager();
    }

    /**
     * @return SymfonyStyle
     */
    protected function io()
    {
        if (!$this->io) {
            $this->io = new SymfonyStyle($this->input(), $this->output());
        }
        return $this->io;
    }

    /**
     * @return InputInterface
     */
    protected function input()
    {
        return Drush::input();
    }

    /**
     * @return OutputInterface
     */
    protected function output()
    {
        return Drush::output();
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
