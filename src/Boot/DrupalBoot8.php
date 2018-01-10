<?php

namespace Drush\Boot;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drush\Log\DrushLog;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\DrupalKernel;
use Drush\Drush;
use Drush\Drupal\DrushServiceModifier;

use Drush\Log\LogLevel;

class DrupalBoot8 extends DrupalBoot implements AutoloaderAwareInterface
{
    use AutoloaderAwareTrait;

    /**
     * @var \Drupal\Core\DrupalKernelInterface
     */
    protected $kernel;

    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function setRequest($request)
    {
        $this->request = $request;
    }

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
        // Are the class constants available?
        if (!$this->hasAutoloader()) {
            throw new \Exception('Cannot access Drupal 8 class constants - Drupal autoloader not loaded yet.');
        }
        // Drush depends on bootstrap being loaded at this point.
        require_once $drupal_root .'/core/includes/bootstrap.inc';
        if (defined('\Drupal::VERSION')) {
            return \Drupal::VERSION;
        }
    }

    public function confPath($require_settings = true, $reset = false)
    {

        if (\Drupal::hasService('kernel')) {
            $site_path = \Drupal::service('kernel')->getSitePath();
        }
        if (!isset($site_path) || empty($site_path)) {
            $site_path = DrupalKernel::findSitePath($this->getRequest(), $require_settings);
        }
        return $site_path;
    }

    public function addLogger()
    {
        // Provide a logger which sends
        // output to drush_log(). This should catch every message logged through every
        // channel.
        $container = \Drupal::getContainer();
        $parser = $container->get('logger.log_message_parser');

        $drushLogger = Drush::logger();
        $logger = new DrushLog($parser, $drushLogger);
        $container->get('logger.factory')->addLogger($logger);
    }

    public function bootstrapDrupalCore($drupal_root)
    {
        $core = DRUPAL_ROOT . '/core';

        return $core;
    }

    public function bootstrapDrupalSiteValidate()
    {
        parent::bootstrapDrupalSiteValidate();
        // Account for users who omit the http:// prefix.
        if (!parse_url($this->uri, PHP_URL_SCHEME)) {
            $this->uri = 'http://' . $this->uri;
        }
        $request = Request::create($this->uri, 'GET', [], [], [], ['SCRIPT_NAME' => '/index.php']);
        $this->setRequest($request);
        $confPath = drush_bootstrap_value('confPath', $this->confPath(true, true));
        drush_bootstrap_value('site', $request->getHttpHost());
        return true;
    }

    public function bootstrapDrupalConfigurationValidate()
    {
        $conf_file = $this->confPath() . '/settings.php';
        if (!file_exists($conf_file)) {
            $msg = dt("Could not find a Drupal settings.php file at !file.", ['!file' => $conf_file]);
            $this->logger->debug($msg);
            // Cant do this because site:install deliberately bootstraps to configure without a settings.php file.
            // return drush_set_error($msg);
        }
        return true;
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

    public function bootstrapDrupalConfiguration(AnnotationData $annotationData = null)
    {
        // Default to the standard kernel.
        $kernel = Kernels::DRUPAL;
        if (!empty($annotationData)) {
            $kernel = $annotationData->get('kernel', Kernels::DRUPAL);
        }
        $classloader = $this->autoloader();
        $request = $this->getRequest();
        $kernel_factory = Kernels::getKernelFactory($kernel);
        /** @var \Drupal\Core\DrupalKernelInterface kernel */
        $this->kernel = $kernel_factory($request, $classloader, 'prod');
        // Include Drush services in the container.
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
        $this->kernel->boot();
        $this->kernel->prepareLegacyRequest($this->getRequest());
        $this->logger->debug(dt('Finished bootstrap of the Drupal Kernel.'));

        parent::bootstrapDrupalFull();
        $this->addLogger();

        // Get a list of the modules to ignore
        $ignored_modules = drush_get_option_list('ignored-modules', []);

        $application = Drush::getApplication();
        $runner = Drush::runner();

        // We have to get the service command list from the container, because
        // it is constructed in an indirect way during the container initialization.
        // The upshot is that the list of console commands is not available
        // until after $kernel->boot() is called.
        $container = \Drupal::getContainer();
        $serviceCommandlist = $container->get(DrushServiceModifier::DRUSH_CONSOLE_SERVICES);
        if ($container->has(DrushServiceModifier::DRUSH_CONSOLE_SERVICES)) {
            foreach ($serviceCommandlist->getCommandList() as $command) {
                if (!$this->commandIgnored($command, $ignored_modules)) {
                    $this->inflect($command);
                    $this->logger->log(LogLevel::DEBUG_NOTIFY, dt('Add a command: !name', ['!name' => $command->getName()]));
                    $application->add($command);
                }
            }
        }
        // Do the same thing with the annotation commands.
        if ($container->has(DrushServiceModifier::DRUSH_COMMAND_SERVICES)) {
            $serviceCommandlist = $container->get(DrushServiceModifier::DRUSH_COMMAND_SERVICES);
            foreach ($serviceCommandlist->getCommandList() as $commandHandler) {
                if (!$this->commandIgnored($commandHandler, $ignored_modules)) {
                    $this->inflect($commandHandler);
                    $this->logger->log(LogLevel::DEBUG_NOTIFY, dt('Add a commandfile class: !name', ['!name' => get_class($commandHandler)]));
                    $runner->registerCommandClass($application, $commandHandler);
                }
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
            $this->kernel->terminate($this->getRequest(), $response);
        }
    }
}
