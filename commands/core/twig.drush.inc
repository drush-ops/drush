<?php

use Drupal\Core\Site\Settings;
use Drupal\Core\Config\NullStorage;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drush\Log\LogLevel;

/**
 * Implements hook_drush_command().
 */
function twig_drush_command() {
  $items['twig-compile'] = array(
    'description' => 'Compile all Twig template(s).',
    'aliases' => array('twigc'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'options' => array(
      'standalone' => 'Run this command on a Drupal codebase without attempt to connect to a working database or installed site. This will process all themes and modules regardless of their enabled state. Requires PHP 7+.',
    ),
    'core' => array('8+'),
  );
  return $items;
}

function drush_twig_compile() {
  $bootstrap = \Drush::bootstrap();
  if (!drush_get_option('standalone')) {
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL);
    $searchpaths = $bootstrap->commandfile_searchpaths(DRUSH_BOOTSTRAP_DRUPAL_FULL);

  }
  else {
    // Scan all modules and themes. By (ab)using DRUSH_BOOTSTRAP_DRUPAL_SITE we
    // lose the ability to exclude modules, but no longer require database access
    // to get the info of active themes. Since compiling templates is really
    // cheap this is a valuable tradeoff.
    // With this method we need to make sure to search core directories too.
    $searchpaths = array(
      'core/themes',
      'core/modules',
      'core/profiles',
      'profiles',
    );
    $searchpaths += $bootstrap->commandfile_searchpaths(DRUSH_BOOTSTRAP_DRUPAL_SITE);
    // We have this command set to use DRUSH_BOOTSTRAP_DRUPAL SITE. This let's us
    // run the command and intervene before further bootstrapping into the
    // _CONFIGURATION and _FULL phases which we do here manually.
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);

    // Intervene in settings.php here to skip all database stuff.
    $settings = Settings::getAll();
    $settings_object = Settings::getInstance();
    $ref = new ReflectionProperty($settings_object, 'storage');
    $ref->setAccessible(TRUE);
    $settings = drush_twig_custom_settings($settings);
    $ref->setValue($settings_object, $settings);

    // Add a service provider which will nullify the KeyValue store for us,
    // because that no longer has a nice convenient $settings override.
    $GLOBALS['conf']['container_service_providers'][] = new class() implements ServiceProviderInterface, ServiceModifierInterface {
      public function alter(ContainerBuilder $container) {
        $container->setParameter('factory.keyvalue', array('default' => 'keyvalue.expirable.null'));
        $container->setParameter('factory.keyvalue.expirable', array('default' => 'keyvalue.expirable.null'));

        $container->register('config.storage', 'Drupal\Core\Config\NullStorage');

        $container->removeDefinition('database');
        $container->register('database')->setSynthetic(TRUE);
      }

      public function register(ContainerBuilder $container) {
        $container->register('keyvalue.expirable.null', 'Drupal\Core\KeyValueStore\KeyValueNullExpirableFactory');
      }
    };

    // Skip the database bootstrapping phase.
    drush_set_context('DRUSH_BOOTSTRAP_VALIDATION_PHASE', DRUSH_BOOTSTRAP_DRUPAL_DATABASE);
    drush_set_context('DRUSH_BOOTSTRAP_PHASE', DRUSH_BOOTSTRAP_DRUPAL_DATABASE);
    // Pretend to have a working site.
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL);

    // Now that we're "bootstrapped" we have to fake-up a database connection
    // so that Twig's service dependency chain can resolve all the way.
    $container = \Drupal::getContainer();
    $container->set('database', new class(NULL) extends Connection {
      public function __construct() {}
      public function queryRange($query, $from, $count, array $args = array(), array $options = array()) {}
      public function queryTemporary($query, array $args = array(), array $options = array()) {}
      public function nextId($existing_id = 0) {}
      public function mapConditionOperator($operator) {}
      public function createDatabase($database) {}
      public function databaseType() {}
      public function driver() {}
    });
  }

  require_once DRUSH_DRUPAL_CORE . "/themes/engines/twig/twig.engine";
  $twig = \Drupal::service('twig');

  foreach ($searchpaths as $searchpath) {
    foreach ($file = drush_scan_directory($searchpath, '/\.html.twig/', array('tests')) as $file) {
      $relative = str_replace(drush_get_context('DRUSH_DRUPAL_ROOT'). '/', '', $file->filename);
      // @todo Dynamically disable twig debugging since there is no good info there anyway.
      $cls = $twig->getTemplateClass($relative);
      $key = $twig->getCache()->generateKey($relative, $cls);
      $content = $twig->compileSource($twig->getLoader()->getSource($relative), $relative);
      $twig->getCache()->write($key, $content);

      drush_log(dt('Compiled twig template !path', array('!path' => $relative)), LogLevel::INFO);
    }
  }
}

/**
 *
 */
function drush_twig_custom_settings($settings) {
  drush_log("Setting up custom settings to evade database access and caching.", LogLevel::DEBUG);
  // Load the null cache backend.
  $settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';

  // Disable all caching.
  $settings['cache']['bins']['default'] = 'cache.backend.null';

  $settings['cache']['bins']['bootstrap'] = 'cache.backend.null';
  $settings['cache']['bins']['config'] = 'cache.backend.null';
  $settings['cache']['bins']['data'] = 'cache.backend.null';
  $settings['cache']['bins']['discovery'] = 'cache.backend.null';
  $settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
  $settings['cache']['bins']['entity'] = 'cache.backend.null';
  $settings['cache']['bins']['menu'] = 'cache.backend.null';
  $settings['cache']['bins']['render'] = 'cache.backend.null';

  $settings['keyvalue_default'] = 'keyvalue.expirable.null';

  // Null out the container cache backend here.
  $settings['bootstrap_container_definition'] = [
    'parameters' => [],
    'services' => [
      'database' => [
        'class' => 'Drupal\Core\Database\Connection',
        'factory' => 'Drupal\Core\Database\Database::getConnection',
        'arguments' => ['default'],
      ],
      'cache.container' => [
        'class' => 'Drupal\Core\Cache\NullBackend',
        'arguments' => ['container'],
      ],
      'cache_tags_provider.container' => [
        'class' => 'Drupal\Core\Cache\DatabaseCacheTagsChecksum',
        'arguments' => ['@database'],
      ],
    ],
  ];

  // Supply our own config storage backend.
  $settings['bootstrap_config_storage'] = 'drush_twig_custom_config';

  return $settings;
}

/**
 *
 */
function drush_twig_custom_config() {
  global $config;

  $config['system.site'] = array(
    'langcode' => drush_get_option('language') ?: 'en',
  );

  $config['simpletest.settings'] = array();
  $config['core.extension'] = array('standard');

  // @TODO: Figure out what to add here.

  return new class() extends NullStorage {
    public function exists($name) {
      global $config;
      return isset($config[$name]);
    }

    public function read($name) {
      global $config;
      return $this->exists($name) ? $config[$name] : FALSE;
    }
  };
}

