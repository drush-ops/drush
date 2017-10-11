<?php
namespace Drush\Commands;

use Drush\Style\DrushStyle;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Robo\Contract\IOAwareInterface;
use Robo\Common\IO;
use Symfony\Component\Console\Input\InputOption;

abstract class DrushCommands implements IOAwareInterface, LoggerAwareInterface
{
    // This is more readable.
    const REQ=InputOption::VALUE_REQUIRED;
    const OPT=InputOption::VALUE_OPTIONAL;

    use LoggerAwareTrait;
    use IO {
        io as roboIo;
    }

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
            if (drush_shell_exec_interactive("less %s", $file)) {
                return;
            } elseif (drush_shell_exec_interactive("more %s", $file)) {
                return;
            } else {
                $this->output()->writeln(file_get_contents($file));
            }
        }
    }
}
