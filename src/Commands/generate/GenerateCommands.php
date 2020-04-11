<?php

namespace Drush\Commands\generate;

use DrupalCodeGenerator\Application;
use DrupalCodeGenerator\Command\Navigation;
use DrupalCodeGenerator\GeneratorFactory;
use DrupalCodeGenerator\Helper\DrupalContext;
use DrupalCodeGenerator\Helper\Dumper;
use DrupalCodeGenerator\Helper\LoggerFactory;
use DrupalCodeGenerator\Helper\QuestionHelper;
use DrupalCodeGenerator\Helper\Renderer;
use DrupalCodeGenerator\Helper\ResultPrinter;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Loader\FilesystemLoader;
use DrupalCodeGenerator\Twig\TwigEnvironment;

/**
 * Drush generate command.
 */
class GenerateCommands extends DrushCommands
{

    /**
     * Generate boilerplate code for modules/plugins/services etc.
     *
     * Drush asks questions so that the generated code is as polished as possible. After
     * generating, Drush lists the files that were created.
     *
     * @command generate
     * @aliases gen
     * @param string $generator A generator name. Omit to pick from available Generators.
     * @option answer Answer to generator question.
     * @option dry-run Output the generated code but not save it to file system.
     * @option destination Absolute path to a base directory for file writing.
     * @usage drush generate
     *  Pick from available generators and then run it.
     * @usage drush generate controller
     *  Generate a controller class for your module.
     * @usage drush generate drush-command-file
     *  Generate a Drush commandfile for your module.
     * @topics docs:generators
     * @bootstrap max
     *
     * @return int
     */
    public function generate($generator = '', $options = ['answer' => [], 'destination' => self::REQ, 'dry-run' => FALSE])
    {
        // Disallow default Symfony console commands.
        if ($generator == 'help' || $generator == 'list') {
            $generator = null;
        }

        $application = $this->createApplication();

        // @todo Use list command instead?
        $application->add(new Navigation());
        $application->setDefaultCommand('navigation');

        // Create an isolated input.
        $argv = ['dcg' , $generator];
        foreach ($options['answer'] as $answer) {
            $argv[] = '--answer='. $answer;
        }
        if ($options['destination']) {
            $argv[] = '--destination=' . $options['destination'];
        }
        if ($options['ansi']) {
            $argv[] = '--ansi';
        }
        if ($options['no-ansi']) {
            $argv[] = '--no-ansi';
        }
        if ($options['dry-run']) {
            $argv[] = '--dry-run';
        }

        return $application->run(new ArgvInput($argv));
    }

    /**
     * Creates Drush generate application.
     */
    private function createApplication(): Application
    {
        $application = new Application('Drupal Code Generator', Drush::getVersion());
        $application->setAutoExit(false);

        $helper_set = new HelperSet([
            new QuestionHelper(),
            new Dumper(new Filesystem()),
            new Renderer(new TwigEnvironment(new FilesystemLoader())),
            new ResultPrinter(TRUE),
            new LoggerFactory(),
            new DrupalContext(\Drupal::getContainer())
        ]);

        $application->setHelperSet($helper_set);

        $generator_factory = new GeneratorFactory(new Filesystem());
        // @todo Filter out DCG generators that do not make sense for Drush.
        $dcg_generators = $generator_factory->getGenerators([Application::ROOT . '/src/Command']);
        $drush_generators = $generator_factory->getGenerators([__DIR__ . '/Generators'], '\Drush\Commands\generate\Generators');
        // @todo Implement generator discovery for this.
        $global_generators = [];
        $module_generators = [];
        $theme_generators = [];

        $generators = array_merge(
            $dcg_generators,
            $drush_generators,
            $global_generators,
            $module_generators,
            $theme_generators
        );
        $application->addCommands($generators);

        return $application;
    }
}
