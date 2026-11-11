<?php

declare(strict_types=1);

namespace Drush\Doctor;

use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Config\DrushConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Helpers for commands that delegate to dr, Drupal core's CLI.
 */
trait DoctorTrait
{
    /**
     * Returns the path to the dr binary, or throws when it is not installed.
     *
     * @param string $command
     *   The name of the command delegating to dr, for the error message.
     */
    protected function doctorBinary(DrushConfig $config, string $command): string
    {
        $binary = $config->get('drush.vendor-dir') . '/bin/dr';
        if (!is_file($binary)) {
            throw new \RuntimeException(sprintf('%s was not found. %s runs dr %s, which requires Drupal core 11.4 or later.', $binary, $command, $command));
        }
        return $binary;
    }

    /**
     * Runs dr with the given arguments, streaming its output, and returns its
     * exit code.
     *
     * The child inherits the decoration and verbosity of the current output,
     * and the environment from siteEnvironment() so that it selects the same
     * site as Drush.
     *
     * @param string[] $arguments
     *   The dr command name followed by its arguments and options.
     */
    protected function runDoctor(string $binary, array $arguments, BootstrapManager $bootstrapManager, OutputInterface $output): int
    {
        $command = [$binary, ...$arguments];
        if ($output->isDecorated()) {
            $command[] = '--ansi';
        }
        if ($output->isVerbose()) {
            $command[] = '-' . str_repeat('v', (int) min(3, $output->getVerbosity() / OutputInterface::VERBOSITY_VERBOSE));
        }

        $process = new Process($command, null, $this->siteEnvironment($bootstrapManager), null, null);
        $process->run(function (string $type, string $buffer) use ($output): void {
            $target = ($type === Process::ERR && $output instanceof ConsoleOutputInterface) ? $output->getErrorOutput() : $output;
            $target->write($buffer);
        });

        return $process->getExitCode() ?? Command::FAILURE;
    }

    /**
     * Environment variables that make dr select the same site as Drush.
     *
     * dr reads DRUPAL_URI for the commands it boots Drupal for. Its
     * cache:rebuild builds the request from $_SERVER instead, which in CLI
     * holds the environment, so the host is passed as HTTP_HOST as well.
     *
     * For the commands it boots Drupal for, dr builds its request without a
     * script name, which keeps DrupalKernel::findSitePath() from selecting a
     * multisite directory from the uri alone. When Drush has bootstrapped to
     * the SITE level it knows the directory, and passes it along as
     * DRUPAL_DEV_SITE_PATH, the override core reads before findSitePath().
     */
    protected function siteEnvironment(BootstrapManager $bootstrapManager): array
    {
        $uri = $bootstrapManager->getUri();
        if (!is_string($uri) || $uri === '') {
            return [];
        }
        if (!str_contains($uri, '://')) {
            $uri = 'http://' . $uri;
        }
        $env = ['DRUPAL_URI' => $uri];
        if ($host = parse_url($uri, PHP_URL_HOST)) {
            $port = parse_url($uri, PHP_URL_PORT);
            $env['HTTP_HOST'] = $host . ($port ? ':' . $port : '');
        }
        if ($bootstrapManager->hasBootstrapped(DrupalBootLevels::SITE)) {
            $env['DRUPAL_DEV_SITE_PATH'] = $bootstrapManager->bootstrap()->confPath();
        }
        return $env;
    }
}
