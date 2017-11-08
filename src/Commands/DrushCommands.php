<?php
namespace Drush\Commands;

use Drush\Style\DrushStyle;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Robo\Common\ConfigAwareTrait;
use Robo\Contract\ConfigAwareInterface;
use Robo\Contract\IOAwareInterface;
use Robo\Common\IO;
use Symfony\Component\Console\Input\InputOption;

abstract class DrushCommands implements IOAwareInterface, LoggerAwareInterface, ConfigAwareInterface
{
    // This is more readable.
    const REQ=InputOption::VALUE_REQUIRED;
    const OPT=InputOption::VALUE_OPTIONAL;

    use LoggerAwareTrait;
    use ConfigAwareTrait {
        // Move aside this method so we can replace. See https://stackoverflow.com/a/37687295.
        getConfig as ConfigAwareGetConfig;
    }
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
     * Replaces same method in ConfigAwareTrait in order to provide a
     * DrushConfig as return type. Helps with IDE completion.
     *
     * @see https://stackoverflow.com/a/37687295.
     *
     * @return \Drush\Config\DrushConfig
     */
    public function getConfig()
    {
        return $this->ConfigAwareGetConfig();
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
