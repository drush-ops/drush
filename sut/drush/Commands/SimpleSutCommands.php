<?php
namespace Drush\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drush\Drush;
use Drush\Log\SuccessInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Site-wide commands for the System-Under-Test site
 */

class SimpleSutCommands extends DrushCommands
{
    /**
     * Show a message.
     *
     * @command sut:simple
     * @hidden
     */
    public function example()
    {
        $this->logger()->notice(dt("This is an example site-wide command committed to the repository in the SUT inside of the 'drush/Commands' directory."));
    }

    /**
     * Replace Drush logger with a custom one.
     *
     * In a real-world implementation, you would likely use `@hook *` instead of `@hook sut:simple`.
     *
     * @hook init sut:simple
     */
    public function customLogger(InputInterface $argv, AnnotationData $annotationData): void
    {
        $verbosityLevelMap = [SuccessInterface::SUCCESS => OutputInterface::VERBOSITY_NORMAL];
        $formatLevelMap = [SuccessInterface::SUCCESS => \Psr\Log\LogLevel::INFO];
        // One could use Monolog if desired.
        // Drush expects custom loggers to always write to stderr, so dont use ConsoleLogger directly,
        $newLogger = new ConsoleLogger(Drush::output(), $verbosityLevelMap, $formatLevelMap);
        $drushLoggerManager = $this->logger();
        $drushLoggerManager->reset()->add('foo', $newLogger);
    }
}
