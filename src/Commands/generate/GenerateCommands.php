<?php

namespace Drush\Commands\generate;

use DrupalCodeGenerator\GeneratorDiscovery;
use DrupalCodeGenerator\Helpers\Dumper;
use DrupalCodeGenerator\Helpers\Renderer;
use DrupalCodeGenerator\TwigEnvironment;
use Drush\Commands\DrushCommands;
use Drush\Commands\generate\Helpers\InputHandler;
use Drush\Commands\generate\Helpers\InputPreprocessor;
use Drush\Commands\generate\Helpers\OutputHandler;
use Drush\Drush;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Dumper as YamlDumper;

/**
 * Drush generate command.
 */
class GenerateCommands extends DrushCommands {

  /**
   * Generate boilerplate code for modules/plugins/services etc.
   *
   * @command generate
   * @aliases gen
   *
   * @param string $generator
   *    Name of the generator to run.
   *
   * @bootstrap DRUSH_BOOTSTRAP_MAX
   *
   * @return string
   *   The command result.
   */
  public function generate($generator) {

    // Disallow default Symfony console commands.
    if ($generator == 'help' || $generator == 'list') {
      $generator = NULL;
    }

    if (!$generator) {
      // @TODO: What shall we do if argument was not provided?
      // Possible variants:
      // 1. - Run Navigation command like DCG does.
      // 2. - Ask the user for the command name (with auto completion).
      // 3. - Display help message.
      // 4. - Display list of available commands ($argv = [$_SERVER['argv'][0], 'list', '--raw'];).
      // 5. - Nothing (throw an exception).
    }

    $application = $this->createApplication();

    // Create an isolated input.
    $argv = [$_SERVER['argv'][0], $generator];
    return $application->run(new ArgvInput($argv));
  }

  /**
   * Creates Drush generate application.
   *
   * @return \Symfony\Component\Console\Application
   *   Symfony console application.
   */
  protected function createApplication() {
    $application = new Application('Drush generate', Drush::getVersion());
    $helperSet = $application->getHelperSet();

    $dumper = new Dumper(new Filesystem(), new YamlDumper());
    $helperSet->set($dumper);

    $twig_loader = new \Twig_Loader_Filesystem();
    $renderer = new Renderer(new TwigEnvironment($twig_loader));
    $helperSet->set($renderer);

    $helperSet->set(new InputHandler());
    $helperSet->set(new OutputHandler());
    $helperSet->set(new InputPreprocessor());

    // Discover generators.
    $discovery = new GeneratorDiscovery(new Filesystem());

    // @todo Discover generators in Drupal modules and themes.
    $dcg_generators = $discovery->getGenerators([DCG_ROOT . '/src/Commands/Drupal_8'], '\DrupalCodeGenerator\Commands\Drupal_8');
    $drush_generators = $discovery->getGenerators([__DIR__ . '/Commands'], '\Drush\Commands\generate\Commands');

    /** @var \Symfony\Component\Console\Command\Command[] $generators */
    $generators = array_merge($dcg_generators, $drush_generators);

    foreach ($generators as $generator) {
      $sub_names = explode(':', $generator->getName());
      if ($sub_names[0] == 'd8') {
        // Remove d8 namespace.
        array_shift($sub_names);
      }
      // @todo Shall we use command alias instead?
      $generator->setName(implode('-', $sub_names));
    }

    $application->addCommands($generators);

    $application->setAutoExit(FALSE);
    return $application;
  }

  /**
   * Drush completion callback.
   *
   * @todo Implement this.
   */
  public function completeGenerators() {

  }

}
