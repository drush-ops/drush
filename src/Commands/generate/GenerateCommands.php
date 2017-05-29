<?php

namespace Drush\Commands\generate;

use DrupalCodeGenerator\GeneratorDiscovery;
use DrupalCodeGenerator\Helper\Dumper;
use DrupalCodeGenerator\Helper\Renderer;
use DrupalCodeGenerator\TwigEnvironment;
use Drush\Commands\DrushCommands;
use Drush\Commands\generate\Helper\InputHandler;
use Drush\Commands\generate\Helper\InputPreprocessor;
use Drush\Commands\generate\Helper\OutputHandler;
use Drush\Drush;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Dumper as YamlDumper;
use Webmozart\PathUtil\Path;

/**
 * Drush generate command.
 */
class GenerateCommands extends DrushCommands {

  /**
   * Generate boilerplate code for modules/plugins/services etc.
   *
   * @command generate
   * @aliases gen
   * @param string $generator Name of the generator to run.
   * @option answers JSON formatted answers
   * @option directory Base directory for file writing.
   *
   * @bootstrap DRUSH_BOOTSTRAP_MAX
   *
   * @return string
   *   The command result.
   */
  public function generate($generator, $options = ['answers' => null, 'directory' =>  null]) {

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
    $argv = [$generator, '--answers=' . escapeshellarg($options['answers']), '--directory=' . $options['directory']];
    return $application->run(new StringInput(implode(' ', $argv)));
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

    $override = NULL;
    if (drush_get_context('DRUSH_AFFIRMATIVE')) {
      $override = TRUE;
    }
    elseif (drush_get_context('DRUSH_NEGATIVE')) {
      $override = FALSE;
    }
    $dumper = new Dumper(new Filesystem(), new YamlDumper(), $override);
    $helperSet->set($dumper);

    $twig_loader = new \Twig_Loader_Filesystem();
    $renderer = new Renderer(new TwigEnvironment($twig_loader));
    $helperSet->set($renderer);

    $helperSet->set(new InputHandler());
    $helperSet->set(new OutputHandler());
    $helperSet->set(new InputPreprocessor());

    // Discover generators.
    $discovery = new GeneratorDiscovery(new Filesystem());

    /**
     * Discover generators.
     */
    $dcg_generators = $discovery->getGenerators([DCG_ROOT . '/src/Command/Drupal_8'], '\DrupalCodeGenerator\Command\Drupal_8');
    $drush_generators = $discovery->getGenerators([__DIR__ . '/Command'], '\Drush\Commands\generate\Command');
    if (drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
      $container = \Drupal::getContainer();
      $module_generators = $container->get('drush.service.generators')->getCommandList();
    }

    /** @var \Symfony\Component\Console\Command\Command[] $generators */
    $generators = array_merge($dcg_generators, $drush_generators, $module_generators);

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
