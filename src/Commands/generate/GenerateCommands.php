<?php

namespace Drush\Commands\generate;

use DrupalCodeGenerator\GeneratorDiscovery;
use DrupalCodeGenerator\Helper\Dumper;
use DrupalCodeGenerator\Helper\Renderer;
use DrupalCodeGenerator\TwigEnvironment;
use Drush\Commands\DrushCommands;
use Drush\Commands\generate\Helper\InputHandler;
use Drush\Commands\generate\Helper\OutputHandler;
use Drush\Commands\help\ListCommands;
use Drush\Drush;
use Drush\Drupal\DrushServiceModifier;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Filesystem\Filesystem;

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
     * @option answers A JSON string containing pairs of question and answers.
     * @option directory Absolute path to a base directory for file writing.
     * @usage drush generate
     *  Pick from available generators and then run it.
     * @usage drush generate controller
     *  Generate a controller class for your module.
     * @usage drush generate drush-command-file
     *  Generate a Drush commandfile for your module.
     * @topics docs:generators
     * @bootstrap max
     */
    public function generate($generator = '', $options = ['answers' => self::REQ, 'directory' => self::REQ])
    {

        // Disallow default Symfony console commands.
        if ($generator == 'help' || $generator == 'list') {
            $generator = null;
        }

        $application = $this->createApplication();
        if (!$generator) {
            $all = $application->all();
            $namespaced = ListCommands::categorize($all);
            $preamble = dt('Run `drush generate [command]` and answer a few questions in order to write starter code to your project.');
            ListCommands::renderListCLI($application, $namespaced, $this->output(), $preamble);
            return null;
        } else {
            // Create an isolated input.
            $argv = [
                $generator,
                '--answers=' . escapeshellarg($options['answers']),
                '--directory=' . $options['directory']
            ];
            return $application->run(new StringInput(implode(' ', $argv)));
        }
    }

    /**
     * Creates Drush generate application.
     *
     * @return \Symfony\Component\Console\Application
     *   Symfony console application.
     */
    protected function createApplication()
    {
        $application = new Application('Drush generate', Drush::getVersion());
        $helperSet = $application->getHelperSet();

        $override = null;
        if (Drush::affirmative()) {
            $override = true;
        } elseif (Drush::negative()) {
            $override = false;
        }
        $dumper = new Dumper(new Filesystem(), $override);
        $helperSet->set($dumper);

        $twig_loader = new \Twig_Loader_Filesystem();
        $renderer = new Renderer(new TwigEnvironment($twig_loader));
        $helperSet->set($renderer);

        $helperSet->set(new InputHandler());
        $helperSet->set(new OutputHandler());

        // Discover generators.
        $discovery = new GeneratorDiscovery(new Filesystem());

        /**
         * Discover generators.
         */
        $dcg_generators = $discovery->getGenerators([DCG_ROOT . '/src/Command/Drupal_8'], '\DrupalCodeGenerator\Command\Drupal_8');
        $drush_generators = $discovery->getGenerators([__DIR__ . '/Generators'], '\Drush\Commands\generate\Generators');
        $module_generators = [];
        if (drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
            $container = \Drupal::getContainer();
            if ($container->has(DrushServiceModifier::DRUSH_GENERATOR_SERVICES)) {
                $module_generators = $container->get(DrushServiceModifier::DRUSH_GENERATOR_SERVICES)->getCommandList();
            }
        }

        /** @var \Symfony\Component\Console\Command\Command[] $generators */
        $generators = array_merge($dcg_generators, $drush_generators, $module_generators);

        foreach ($generators as $generator) {
            $sub_names = explode(':', $generator->getName());
            if ($sub_names[0] == 'd8') {
                // Remove d8 namespace.
                array_shift($sub_names);
            }
            $new_name = implode('-', $sub_names);
            $generator->setName($new_name);
            // Remove alias if it is same as new name.
            if ($aliases = $generator->getAliases()) {
                foreach ($aliases as $key => $alias) {
                    // These dont work due to Console 'guessing' wrong.
                    if ($alias == 'module' || $alias == 'theme') {
                        unset($aliases[$key]);
                    }
                }
                $generator->setAliases(array_diff($aliases, [$new_name]));
            }
        }

        $application->addCommands($generators);

        $application->setAutoExit(false);
        return $application;
    }
}
