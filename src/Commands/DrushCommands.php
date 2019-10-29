<?php
namespace Drush\Commands;

use Drush\Drush;
use Drush\Style\DrushStyle;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Drush\Config\ConfigAwareTrait;
use Robo\Contract\ConfigAwareInterface;
use Robo\Contract\IOAwareInterface;
use Robo\Common\IO;
use Symfony\Component\Console\Input\InputOption;
use Consolidation\SiteProcess\ProcessManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManagerAwareInterface;

abstract class DrushCommands implements IOAwareInterface, LoggerAwareInterface, ConfigAwareInterface, ProcessManagerAwareInterface
{
    use ProcessManagerAwareTrait;

    // This is more readable.
    const REQ=InputOption::VALUE_REQUIRED;
    const OPT=InputOption::VALUE_OPTIONAL;

    // Common exit codes.
    const EXIT_SUCCESS = 0;
    const EXIT_FAILURE = 1;

    use LoggerAwareTrait;
    use ConfigAwareTrait;
    use IO {
        io as roboIo;
    }

    public function __construct()
    {
    }

    /**
     * Override Robo's IO function with our custom style.
     */
    protected function io()
    {
        if (!$this->io) {
            // Specify our own Style class when needed.
            $this->io = new DrushStyle($this->input(), $this->output());
        }
        return $this->io;
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
        if ((substr($file, -4) == ".htm") || (substr($file, -5) == ".html")) {
            $tmp_file = drush_tempnam(basename($file));
            file_put_contents($tmp_file, drush_html_to_text(file_get_contents($file)));
            $file = $tmp_file;
        }

        if (self::input()->isInteractive()) {
            ;
            $process = $this->processManager()->process(['less', $file])->setTty(true);
            if ($process->run() === 0) {
                return;
            } else {
                $process = $this->processManager()->process(['more', $file]);
                if ($process->run() === 0) {
                    return;
                } else {
                    $this->output()->writeln(file_get_contents($file));
                }
            }
        }
    }
}
