<?php

namespace Unish\Controllers;

use Drush\Application;
use Drush\Config\Environment;
use Drush\Drush;
use Drush\Preflight\Preflight;
use Drush\Preflight\PreflightLog;
use Drush\Runtime\DependencyInjection;
use Drush\Runtime\Runtime;
use PHPUnit\Framework\TestResult;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * The runtime controller manages the Drush runtime for Unish,
 * ensuring that there is only one copy of the DI container et. al.,
 * and that we only bootstrap Drupal once.
 *
 * This class follows the singleton pattern, which is typically
 * deprecated, but suits our current purposes very well.
 */
class RuntimeController
{
    /** @var RuntimeController */
    private static $instance;

    /** @var Runtime */
    protected $runtime;

    /** @var Preflight */
    protected $preflight;

    protected $output;

    protected $container;

    protected $input;

    /** @var Application */
    protected $application;

    protected $bootstrap;

    protected $loader;

    private function __construct()
    {
        // Create a reusable output buffer
        $this->output = new \Drush\Symfony\BufferedConsoleOutput();
    }

    public static function instance()
    {
        if (!static::$instance) {
            static::$instance = new self();
        }
        return static::$instance;
    }

    public function initialized()
    {
        return $this->application != null;
    }

    public function application($root, $argv)
    {
        $this->initializeRuntime($root, $argv);
        return $this->application;
    }

    public function stdinHandler()
    {
        return $this->container->get('stdinHandler');
    }

    public function input()
    {
        return $this->input;
    }

    public function output()
    {
        return $this->output;
    }

    public function loader()
    {
        if (!$this->loader) {
            $this->loader = require PHPUNIT_COMPOSER_INSTALL;
        }
        return $this->loader;
    }

    protected function initializeRuntime($root, $argv)
    {
        // Create our objects
        $loader = $this->loader();
        $environment = new Environment(Path::getHomeDirectory(), $root, PHPUNIT_COMPOSER_INSTALL);
        $environment->setConfigFileVariant(Drush::getMajorVersion());
        $environment->setLoader($loader);
        $environment->applyEnvironment();
        $preflightLog = new PreflightLog(new NullOutput());
        $this->preflight = new Preflight($environment, null, null, $preflightLog);
        $di = new DependencyInjection();

        // Begin our version of Runtime::doRun
        $status = $this->preflight->preflight($argv);

        // If preflight signals that we are done, then exit early.
        if ($status !== false) {
            return $status;
        }

        $commandfileSearchpath = $this->preflight->getCommandFilePaths();
        $this->preflight->config()->set('runtime.commandfile.paths', $commandfileSearchpath);

        // Require the Composer autoloader for Drupal (if different)
        $loader = $this->preflight->loadSiteAutoloader();

        // Create the Symfony Application et. al.
        $this->input = $this->preflight->createInput();
        $this->application = new \Drush\Application('Drush Commandline Tool (Unish-scaffolded)', Drush::getVersion());

        // Set up the DI container.
        $this->container = $di->initContainer(
            $this->application,
            $this->preflight->config(),
            $this->input,
            $this->output,
            $loader,
            $this->preflight->drupalFinder(),
            $this->preflight->aliasManager()
        );

        // At this point, Runtime::doRun installs error and
        // shutdown handlers. We do not need or want those here.

        // Ensure that the bootstrap object gets its root and uri set
        $this->application->refineUriSelection($root);

        // Get the bootstrap manager and either:
        // - re-inject the cached bootstrap object into the bootstrap manager
        // - do a full bootstrap and cache the bootstrap object
        $this->handleBootstrap();

        // Add global options and copy their values into Config.
        $this->application->configureGlobalOptions();

        // Configure the application object and register all of the commandfiles
        // from the search paths we found above.  After this point, the input
        // and output objects are ready & we can start using the logger, etc.
        $this->application->configureAndRegisterCommands($this->input, $this->output, $commandfileSearchpath);
    }

    protected function handleBootstrap()
    {
        $manager = $this->container->get('bootstrap.manager');

        // If we have a cached bootstrap object that has already bootstrapped
        // Drupal, re-inject it into the bootstrap manager.
        if ($this->bootstrap) {
            $manager->injectBootstrap($this->bootstrap);
            return;
        }

        // Do a full bootstrap and cache the result.
        // Note that we do not pass any auxiliary data here, so the
        // bootstrap manager will always use the DrupalKernel. This
        // is necessary, since we cannot bootstrap more than once.
        $manager->bootstrapToPhase('full');
        $this->bootstrap = $manager->bootstrap();
    }
}
