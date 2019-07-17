<?php

namespace Drush\Boot;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drupal\Core\Database\Database;
use Drupal\Core\DrupalKernel;
use Drush\Drupal\DrushServiceModifier;
use Drush\Drush;
use Drush\Log\DrushLog;
use Drush\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\PathUtil\Path;
use Psr\Log\LoggerInterface;

class DrupalBoot8 extends DrupalBoot implements AutoloaderAwareInterface
{
    use AutoloaderAwareTrait;

    /**
     * @var LoggerInterface
     */
    protected $drupalLoggerAdapter;

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

    /**
     * @return \Drupal\Core\DrupalKernelInterface
     */
    public function getKernel()
    {
        return $this->kernel;
    }

    /**
     * Sometimes (e.g. in the integration tests), the DrupalBoot
     * object will be cached, and re-injected into a fresh set
     * of preflight / bootstrap objects. When this happens, the
     * new Drush logger will be injected into the boot object. If
     * this happens after we have created the Drupal logger adapter
     * (i.e., after bootstrapping Drupal), then we also need to
     * update the logger reference in that adapter.
     */
    public function setLogger(LoggerInterface $logger)
    {
        if ($this->drupalLoggerAdapter) {
            $this->drupalLoggerAdapter->setLogger($logger);
        }
        parent::setLogger($logger);
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
        $this->drupalLoggerAdapter = new DrushLog($parser, $drushLogger);
        $container->get('logger.factory')->addLogger($this->drupalLoggerAdapter);
    }

    public function bootstrapDrupalCore(BootstrapManager $manager, $drupal_root)
    {
        return Path::join($drupal_root, 'core');
    }

    public function bootstrapDrupalSiteValidate(BootstrapManager $manager)
    {
        parent::bootstrapDrupalSiteValidate($manager);

        // Normalize URI.
        $uri = rtrim($this->uri, '/') . '/';
        $parsed_url = parse_url($uri);

        // Account for users who omit the http:// prefix.
        if (empty($parsed_url['scheme'])) {
            $this->uri = 'http://' . $this->uri;
            $parsed_url = parse_url('http://' . $uri);
        }

        $server = [
            'SCRIPT_FILENAME' => getcwd() . '/index.php',
            'SCRIPT_NAME' => isset($parsed_url['path']) ? $parsed_url['path'] . 'index.php' : '/index.php',
        ];
        $request = Request::create($this->uri, 'GET', [], [], [], $server);
        $this->setRequest($request);
        return true;
    }

    /**
     * Called by bootstrapDrupalSite to do the main work
     * of the drush drupal site bootstrap.
     */
    public function bootstrapDoDrupalSite(BootstrapManager $manager)
    {
        $this->logger->log(LogLevel::BOOTSTRAP, dt("Initialized Drupal site !site at !site_root", ['!site' => $this->getRequest()->getHttpHost(), '!site_root' => $this->confPath()]));
    }

    public function bootstrapDrupalConfigurationValidate(BootstrapManager $manager)
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

    public function bootstrapDrupalDatabaseValidate(BootstrapManager $manager)
    {
        // Drupal requires PDO, and Drush requires php 5.6+ which ships with PDO
        // but PHP may be compiled with --disable-pdo.
        if (!class_exists('\PDO')) {
            $this->logger->log(LogLevel::BOOTSTRAP, dt('PDO support is required.'));
            return false;
        }

        try {
            // @todo Log queries in addition to logging failure messages?
            $connection = Database::getConnection();
            $connection->query('SELECT 1;');
        } catch (\Exception $e) {
            $this->logger->log(LogLevel::BOOTSTRAP, 'Unable to connect to database. More information may be available by running `drush status`. This may occur when Drush is trying to bootstrap a site that has not been installed or does not have a configured database. In this case you can select another site with a working database setup by specifying the URI to use with the --uri parameter on the command line. See `drush topic docs-aliases` for details.');
            return false;
        }
        if (!$connection->schema()->tableExists('key_value')) {
            $this->logger->log(LogLevel::BOOTSTRAP, 'key_value table not found. Database may be empty.');
            return false;
        }
        return true;
    }

    public function bootstrapDrupalDatabase(BootstrapManager $manager)
    {
        // D8 omits this bootstrap level as nothing special needs to be done.
        parent::bootstrapDrupalDatabase($manager);
    }

    public function bootstrapDrupalConfiguration(BootstrapManager $manager, AnnotationData $annotationData = null)
    {
        // Default to the standard kernel.
        $kernel = Kernels::DRUPAL;
        if (!empty($annotationData)) {
            $kernel = $annotationData->get('kernel', Kernels::DRUPAL);
        }
        $classloader = $this->autoloader();
        $request = $this->getRequest();
        $kernel_factory = Kernels::getKernelFactory($kernel);
        $allow_dumping = $kernel !== Kernels::UPDATE;
        /** @var \Drupal\Core\DrupalKernelInterface kernel */
        $this->kernel = $kernel_factory($request, $classloader, 'prod', $allow_dumping);
        // Include Drush services in the container.
        // @see Drush\Drupal\DrupalKernel::addServiceModifier()
        $this->kernel->addServiceModifier(new DrushServiceModifier());

        // Unset drupal error handler and restore Drush's one.
        restore_error_handler();

        // Disable automated cron if the module is enabled.
        $GLOBALS['config']['automated_cron.settings']['interval'] = 0;

        parent::bootstrapDrupalConfiguration($manager);
    }

    public function bootstrapDrupalFull(BootstrapManager $manager)
    {
        $this->logger->debug(dt('Start bootstrap of the Drupal Kernel.'));
        $this->kernel->boot();
        $this->kernel->preHandle($this->getRequest());
        $this->logger->debug(dt('Finished bootstrap of the Drupal Kernel.'));

        parent::bootstrapDrupalFull($manager);
        $this->addLogger();
        $this->addDrupalModuleDrushCommands($manager);
    }

    public function addDrupalModuleDrushCommands($manager)
    {
        $application = Drush::getApplication();
        $runner = Drush::runner();

        // We have to get the service command list from the container, because
        // it is constructed in an indirect way during the container initialization.
        // The upshot is that the list of console commands is not available
        // until after $kernel->boot() is called.
        $container = \Drupal::getContainer();

        // Set the command info alterers.
        if ($container->has(DrushServiceModifier::DRUSH_COMMAND_INFO_ALTERER_SERVICES)) {
            $serviceCommandInfoAltererlist = $container->get(DrushServiceModifier::DRUSH_COMMAND_INFO_ALTERER_SERVICES);
            $commandFactory = Drush::commandFactory();
            foreach ($serviceCommandInfoAltererlist->getCommandList() as $altererHandler) {
                $commandFactory->addCommandInfoAlterer($altererHandler);
                $this->logger->debug(dt('Commands are potentially altered in !class.', ['!class' => get_class($altererHandler)]));
            }
        }

        $serviceCommandlist = $container->get(DrushServiceModifier::DRUSH_CONSOLE_SERVICES);
        if ($container->has(DrushServiceModifier::DRUSH_CONSOLE_SERVICES)) {
            foreach ($serviceCommandlist->getCommandList() as $command) {
                $manager->inflect($command);
                $this->logger->log(LogLevel::DEBUG_NOTIFY, dt('Add a command: !name', ['!name' => $command->getName()]));
                $application->add($command);
            }
        }
        // Do the same thing with the annotation commands.
        if ($container->has(DrushServiceModifier::DRUSH_COMMAND_SERVICES)) {
            $serviceCommandlist = $container->get(DrushServiceModifier::DRUSH_COMMAND_SERVICES);
            foreach ($serviceCommandlist->getCommandList() as $commandHandler) {
                $manager->inflect($commandHandler);
                $this->logger->log(LogLevel::DEBUG_NOTIFY, dt('Add a commandfile class: !name', ['!name' => get_class($commandHandler)]));
                $runner->registerCommandClass($application, $commandHandler);
            }
        }
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
