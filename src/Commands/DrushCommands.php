<?php

declare(strict_types=1);

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\AnnotatedCommand\Output\OutputAwareInterface;
use Consolidation\AnnotatedCommand\State\SavableState;
use Consolidation\AnnotatedCommand\State\State;
use Consolidation\Config\ConfigAwareInterface;
use Consolidation\SiteProcess\ProcessManagerAwareInterface;
use Consolidation\SiteProcess\ProcessManagerAwareTrait;
use Drush\Attributes as CLI;
use Drush\Config\ConfigAwareTrait;
use Drush\Drush;
use Drush\Exec\ExecTrait;
use Drush\Log\DrushLoggerManager;
use Drush\SiteAlias\ProcessManager;
use Drush\Style\DrushStyle;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use JetBrains\PhpStorm\Deprecated;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputAwareInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;

#[Deprecated('See https://www.drush.org/latest/commands/')]
abstract class DrushCommands implements OutputAwareInterface, InputAwareInterface, SavableState, LoggerAwareInterface, ConfigAwareInterface, ProcessManagerAwareInterface
{
    use ProcessManagerAwareTrait;
    use ExecTrait;
    use ConfigAwareTrait;
    use LoggerAwareTrait;

    // This is more readable.
    const REQ = InputOption::VALUE_REQUIRED;
    const OPT = InputOption::VALUE_OPTIONAL;

    // Common exit codes.
    const EXIT_SUCCESS = 0;
    const EXIT_FAILURE = 1;
    // Used to signal that the command completed successfully, but we still want to indicate a failure to the caller.
    const EXIT_FAILURE_WITH_CLARITY = 3;

    protected ?CommandData $commandData = null;
    protected ?InputInterface $input = null;
    protected ?OutputInterface $output = null;
    protected ?SymfonyStyle $io = null;

    public function __construct()
    {
    }

    public function currentState()
    {
        return new class($this, $this->input, $this->output, $this->io) implements State {
            public function __construct(
                protected $obj,
                protected $input,
                protected $output,
                protected $io,
            ) {
            }

            public function restore()
            {
                $this->obj->restoreState($this->input, $this->output, $this->io);
            }
        };
    }

    public function restoreState(?InputInterface $input = null, ?OutputInterface $output = null, ?SymfonyStyle $io = null)
    {
        $this->setInput($input);
        $this->setOutput($output);
        $this->io = $io;

        return $this;
    }

    public function setInput(InputInterface $input): void
    {
        if ($input != $this->input) {
            $this->io = null;
        }
        $this->input = $input;
    }

    public function setOutput(OutputInterface $output)
    {
        if ($output != $this->output) {
            $this->io = null;
        }
        $this->output = $output;

        return $this;
    }

    protected function input(): InputInterface
    {
        if (!isset($this->input)) {
            $this->setInput(new ArgvInput());
        }
        return $this->input;
    }

    protected function output(): OutputInterface
    {
        if (!isset($this->output)) {
            $this->setOutput(new NullOutput());
        }
        return $this->output;
    }

    /**
     * Override to provide a DrushStyle instance.
     */
    protected function io(): DrushStyle
    {
        trigger_deprecation('drush/drush', '14.0.0', 'Convert to a Console command and build a DrushStyle instance. See https://www.drush.org/latest/commands/.');
        if (!$this->io) {
            // Specify our own Style class when needed.
            $this->io = new DrushStyle($this->input(), $this->output());
        }
        assert($this->io instanceof DrushStyle);
        return $this->io;
    }

    /**
     * Returns a logger object.
     */
    public function logger(): ?DrushLoggerManager
    {
        trigger_deprecation('drush/drush', '14.0.0', 'Convert to a Console command and inject a Logger. See https://www.drush.org/latest/commands/.');
        assert(is_null($this->logger) || $this->logger instanceof DrushLoggerManager, 'Instead of using replacing Drush\'s logger, use $this->add() on DrushLoggerManager to add a custom logger. See https://github.com/drush-ops/drush/pull/5022');
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
    public function preHook(CommandData $commandData): void
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

    /**
     * This method overrides the trait in order to provide a more specific return type.
     */
    public function processManager(): ProcessManager
    {
        trigger_deprecation('drush/drush', '14.0.0', 'Convert to a Console command and inject a ProcessManager. See https://www.drush.org/latest/commands/.');
        return $this->processManager;
    }
}
