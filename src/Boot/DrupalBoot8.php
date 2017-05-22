<?php

namespace Drush\Boot;

use DrupalCodeGenerator\GeneratorDiscovery;
use Drush\Generate\DrushGenerator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\DrupalKernel;
use Drush\Drush;
use Drush\Drupal\DrupalKernel as DrushDrupalKernel;
use Drush\Drupal\DrushServiceModifier;

use Drush\Log\LogLevel;

class DrupalBoot8 extends DrupalBoot
{

      /**
       * @var \Drupal\Core\DrupalKernelInterface
       */
    protected $kernel;

      /**
       * @var \Symfony\Component\HttpFoundation\Request
       */
    protected $request;

    public function validRoot($path)
    {
        if (!empty($path) && is_dir($path) && file_exists($path . '/autoload.php')) {
            // Additional check for the presence of core/composer.json to
            // grant it is not a Drupal 7 site with a base folder named "core".
            $candidate = 'core/includes/common.inc';
            if (file_exists($path . '/' . $candidate) && file_exists($path . '/core/core.services.yml')) {
                if (file_exists($path . '/core/misc/drupal.js') || file_exists($path . '/core/assets/js/drupal.js')) {
                    return $candidate;
                }
            }
        }
    }

    public function getVersion($drupal_root)
    {
        // Load the autoloader so we can access the class constants.
        drush_drupal_load_autoloader($drupal_root);
        // Drush depends on bootstrap being loaded at this point.
        require_once $drupal_root .'/core/includes/bootstrap.inc';
        if (defined('\Drupal::VERSION')) {
            return \Drupal::VERSION;
        }
    }

    public function getProfile()
    {
        return drupal_get_profile();
    }

    public function confPath($require_settings = true, $reset = false, Request $request = null)
    {
        if (!isset($request)) {
            if (\Drupal::hasRequest()) {
                $request = \Drupal::request();
            } // @todo Remove once external CLI scripts (Drush) are updated.
            else {
                $request = Request::createFromGlobals();
            }
        }
        if (\Drupal::hasService('kernel')) {
            $site_path = \Drupal::service('kernel')->getSitePath();
        }
        if (!isset($site_path) || empty($site_path)) {
            $site_path = DrupalKernel::findSitePath($request, $require_settings);
        }
        return $site_path;
    }

    public function addLogger()
    {
        // If we're running on Drupal 8 or later, we provide a logger which will send
        // output to drush_log(). This should catch every message logged through every
        // channel.
        $container = \Drupal::getContainer();
        $parser = $container->get('logger.log_message_parser');

        $drushLogger = Drush::logger();
        $logger = new \Drush\Log\DrushLog($parser, $drushLogger);
        $container->get('logger.factory')->addLogger($logger);
    }

    public function contribModulesPaths()
    {
        return array(
        $this->confPath() . '/modules',
        'sites/all/modules',
        'modules',
        );
    }

    /**
     * @return array of strings - paths to directories where contrib
     * themes can be found
     */
    public function contribThemesPaths()
    {
        return array(
        $this->confPath() . '/themes',
        'sites/all/themes',
        'themes',
        );
    }

    public function bootstrapDrupalCore($drupal_root)
    {
        $core = DRUPAL_ROOT . '/core';

        return $core;
    }

    public function bootstrapDrupalDatabaseValidate()
    {
        return parent::bootstrapDrupalDatabaseValidate() && $this->bootstrapDrupalDatabaseHasTable('key_value');
    }

    public function bootstrapDrupalDatabase()
    {
        // D8 omits this bootstrap level as nothing special needs to be done.
        parent::bootstrapDrupalDatabase();
    }

    public function bootstrapDrupalConfiguration()
    {
        $this->request = Request::createFromGlobals();
        $classloader = drush_drupal_load_autoloader(DRUPAL_ROOT);
        // @todo - use Request::create() and then no need to set PHP superglobals
        $kernelClass = new \ReflectionClass('\Drupal\Core\DrupalKernel');
        if ($kernelClass->hasMethod('addServiceModifier')) {
            $this->kernel = DrupalKernel::createFromRequest($this->request, $classloader, 'prod', DRUPAL_ROOT);
        } else {
            $this->kernel = DrushDrupalKernel::createFromRequest($this->request, $classloader, 'prod', DRUPAL_ROOT);
        }
        // @see Drush\Drupal\DrupalKernel::addServiceModifier()
        $this->kernel->addServiceModifier(new DrushServiceModifier());

        // Unset drupal error handler and restore Drush's one.
        restore_error_handler();

        // Disable automated cron if the module is enabled.
        $GLOBALS['config']['automated_cron.settings']['interval'] = 0;

        parent::bootstrapDrupalConfiguration();
    }

    public function bootstrapDrupalFull()
    {
        $this->logger->debug(dt('Start bootstrap of the Drupal Kernel.'));
        // TODO: do we need to do ob_start any longer?
        if (!drush_get_context('DRUSH_QUIET', false)) {
            ob_start();
        }
        $this->kernel->boot();
        $this->kernel->prepareLegacyRequest($this->request);
        if (!drush_get_context('DRUSH_QUIET', false)) {
            ob_end_clean();
        }
        $this->logger->debug(dt('Finished bootstrap of the Drupal Kernel.'));

        parent::bootstrapDrupalFull();

        // Get a list of the modules to ignore
        $ignored_modules = drush_get_option_list('ignored-modules', array());

        $this->addGeneratorsToApplication();


        // We have to get the service command list from the container, because
        // it is constructed in an indirect way during the container initialization.
        // The upshot is that the list of console commands is not available
        // until after $kernel->boot() is called.
        $container = \Drupal::getContainer();
        $serviceCommandlist = $container->get('drush.service.consolecommands');
        foreach ($serviceCommandlist->getCommandList() as $command) {
            if (!$this->commandIgnored($command, $ignored_modules)) {
                $this->inflect($command);
                $this->logger->log(LogLevel::DEBUG_NOTIFY, dt('Add a command: !name', ['!name' => $command->getName()]));
                annotationcommand_adapter_cache_module_console_commands($command);
            }
        }
        // Do the same thing with the annotation commands.
        $serviceCommandlist = $container->get('drush.service.consolidationcommands');
        foreach ($serviceCommandlist->getCommandList() as $commandhandler) {
            if (!$this->commandIgnored($commandhandler, $ignored_modules)) {
                $this->inflect($commandhandler);
                $this->logger->log(LogLevel::DEBUG_NOTIFY, dt('Add a commandhandler: !name', ['!name' => get_class($commandhandler)]));
                annotationcommand_adapter_cache_module_service_commands($commandhandler);
            }
        }
    }

    public function commandIgnored($command, $ignored_modules)
    {
        if (empty($ignored_modules)) {
            return false;
        }
        $ignored_regex = '#\\\\(' . implode('|', $ignored_modules) . ')\\\\#';
        $class = new \ReflectionClass($command);
        $commandNamespace = $class->getNamespaceName();
        return preg_match($ignored_regex, $commandNamespace);
    }

    /**
     * {@inheritdoc}
     */
    public function terminate()
    {
        parent::terminate();

        if ($this->kernel) {
            $response = Response::create('');
            $this->kernel->terminate($this->request, $response);
        }
    }

  /**
   * Discover and add generator commands provided by DCG an Drush.
   */
  public function addGeneratorsToApplication() {
    // Register Vendor directories.
    $commands_directories = [DCG_ROOT . '/src/Commands'];
    $twig_directories = [DCG_ROOT . '/src/Templates'];
    $discovery = new GeneratorDiscovery(new Filesystem());
    $generators = $discovery->getGenerators($commands_directories, $twig_directories);
    // Register Drush directories.
    $commands_directories = [dirname(__DIR__) . '/Generate/Commands'];
    $twig_directories = [dirname(__DIR__) . '/Generate/Templates'];
    $discovery = new GeneratorDiscovery(new Filesystem(), '\Drush\Generate\Commands');
    $generators = array_merge($discovery->getGenerators($commands_directories, $twig_directories), $generators);

    foreach ($generators as $generator) {
      $name = $generator->getName();
      if (strpos($name, 'd7:') === FALSE && !in_array($name, ['other:drush-command', 'other:drupal-console-command']))
      {
        $drushGenerator = DrushGenerator::create($twig_directories);
        $nameNew = str_replace(['d8:', 'other:'], 'gen-', $name);
        $nameNew = str_replace(':', '-', $nameNew);
        $drushGenerator = $drushGenerator->setName($nameNew);
        $drushGenerator = $drushGenerator->setDescription($generator->getDescription());
        $drushGenerator->generator = $generator;
        $drushGenerators[] = $drushGenerator;
        $def = $generator->getDefinition();
        $optiondef = $def->getOption('destination');
        // How to remove shortcut from $optiondef?
      }
    }
    $application = Drush::getApplication();
    $application->addCommands($drushGenerators);
  }
}
