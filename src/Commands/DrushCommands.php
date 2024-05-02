<?php

declare(strict_types=1);

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\SiteProcess\ProcessManagerAwareInterface;
use Consolidation\SiteProcess\ProcessManagerAwareTrait;
use Drush\Attributes as CLI;
use Drush\Config\ConfigAwareTrait;
use Drush\Drush;
use Drush\Exec\ExecTrait;
use Drush\Log\DrushLoggerManager;
use Drush\Style\DrushStyle;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use JetBrains\PhpStorm\Deprecated;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Robo\Common\IO;
use Robo\Contract\ConfigAwareInterface;
use Robo\Contract\IOAwareInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;

abstract class DrushCommands implements IOAwareInterface, LoggerAwareInterface, ConfigAwareInterface, ProcessManagerAwareInterface
{
    use ProcessManagerAwareTrait;
    use ExecTrait;
    use ConfigAwareTrait;
    use LoggerAwareTrait;
    use IO {
        io as roboIo;
    }

    // This is more readable.
    const REQ = InputOption::VALUE_REQUIRED;
    const OPT = InputOption::VALUE_OPTIONAL;

    // Common exit codes.
    const EXIT_SUCCESS = 0;
    const EXIT_FAILURE = 1;
    // Used to signal that the command completed successfully, but we still want to indicate a failure to the caller.
    const EXIT_FAILURE_WITH_CLARITY = 3;

    protected ?CommandData $commandData = null;

    public function __construct()
    {
    }

    /**
     * Override Robo's IO function with our custom style.
     */
    protected function io(): SymfonyStyle
    {
        if (!$this->io) {
            // Specify our own Style class when needed.
            $this->io = new DrushStyle($this->input(), $this->output());
        }
        return $this->io;
    }

    /**
     * Sets a logger, if none is available yet.
     */
    #[Deprecated('Use logger() in Drush 13+')]
    public function setLoggerIfEmpty(LoggerInterface $logger): void
    {
        if ($this->logger === null) {
            $this->setLogger($logger);
        }
    }

    /**
     * Returns a logger object.
     */
    protected function logger(): ?DrushLoggerManager
    {
        return $this->logger;
    }

    /**
     * Print the contents of a file.
     *
     * @param string $file
     *   Full path to a file.
     */
    protected function printFile(string $file): void
    {
        if (str_ends_with($file, ".htm") || str_ends_with($file, ".html")) {
            $tmp_file = drush_tempnam(basename($file));
            file_put_contents($tmp_file, drush_html_to_text(file_get_contents($file)));
            $file = $tmp_file;
        }

        if (self::input()->isInteractive()) {
            if (self::programExists('less')) {
                $process = $this->processManager()->process(['less', $file])->setTty(true);
                if ($process->run() === 0) {
                    return;
                }
            }
        }
        $this->output()->writeln(file_get_contents($file));
    }

    /**
     * Persist commandData for use in primary command callback. Used by 'topic' commands.
     */
    #[CLI\Hook(type: HookManager::PRE_COMMAND_HOOK, target: '*')]
    public function preHook(CommandData $commandData)
    {
        $this->commandData = $commandData;
    }

    /**
     * Print the contents of a file. The path comes from the @topic annotation.
     *
     * @param CommandData $commandData
     *   Full path to a file.
     */
    protected function printFileTopic(CommandData $commandData)
    {
        $file = $commandData->annotationData()->get('topic');
        $this->printFile(Path::makeAbsolute($file, dirname($commandData->annotationData()->get('_path'))));
    }

    /**
     * Get a Guzzle handler stack that uses the Drush logger.
     *
     * @see https://stackoverflow.com/questions/32681165/how-do-you-log-all-api-calls-using-guzzle-6.
     */
    protected function getStack(): HandlerStack
    {
        $stack = HandlerStack::create();
        $stack->push(Middleware::log($this->logger(), new MessageFormatter(Drush::debug() ? MessageFormatter::DEBUG : MessageFormatter::SHORT)));
        return $stack;
    }
}
