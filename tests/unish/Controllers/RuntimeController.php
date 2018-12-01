<?php

namespace Unish\Controllers;

use Drush\Config\Environment;
use Drush\Drush;
use Drush\Preflight\Preflight;
use Drush\Runtime\DependencyInjection;
use Drush\Runtime\Runtime;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use PHPUnit\Framework\TestResult;
use Unish\Utils\OutputUtilsTrait;
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

    protected $application;

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

    public function application($root)
    {
        if (!$this->application) {
            $this->initializeRuntime($root);
        }
        return $this->application;
    }

    public function output()
    {
        return $this->output;
    }

    protected function initializeRuntime($root)
    {
        // Create our objects
        $loader = require PHPUNIT_COMPOSER_INSTALL;
        $environment = new Environment(Path::getHomeDirectory(), $root, PHPUNIT_COMPOSER_INSTALL);
        $environment->setConfigFileVariant(Drush::getMajorVersion());
        $environment->setLoader($loader);
        $environment->applyEnvironment();
        $this->preflight = new Preflight($environment);
        $di = new DependencyInjection();

        // Set up the invariant section of our argv for preflight
        $argv = [
            'drush',
            'version',
            "--root=$root",
            '--uri=dev',
            '--debug',
        ];

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
        $input = $this->preflight->createInput();
        $this->application = new \Drush\Application('Drush Commandline Tool (Unish-scaffolded)', Drush::getVersion());

        // Set up the DI container.
        $this->container = $di->initContainer(
            $this->application,
            $this->preflight->config(),
            $input,
            $this->output,
            $loader,
            $this->preflight->drupalFinder(),
            $this->preflight->aliasManager()
        );

        // Note that at this point, Runtime::doRun installs error and
        // shutdown handlers. We do not need or want those here.

        // Now that the DI container has been set up, the Application object will
        // have a reference to the bootstrap manager et. al., so we may use it
        // as needed. Tell the application to coordinate between the Bootstrap
        // manager and the alias manager to select a more specific URI, if
        // one was not explicitly provided earlier in the preflight.
        $this->application->refineUriSelection($this->preflight->environment()->cwd());

        // Add global options and copy their values into Config.
        $this->application->configureGlobalOptions();

        // Configure the application object and register all of the commandfiles
        // from the search paths we found above.  After this point, the input
        // and output objects are ready & we can start using the logger, etc.
        $this->application->configureAndRegisterCommands($input, $this->output, $commandfileSearchpath);
    }
}
