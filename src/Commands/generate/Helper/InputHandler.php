<?php

namespace Drush\Commands\generate\Helper;

use DrupalCodeGenerator\Helper\InputHandler as BaseInputHandler;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generators input handler.
 */
class InputHandler extends BaseInputHandler
{

    /**
     * {@inheritdoc}
     */
    public function collectVars(InputInterface $input, OutputInterface $output, array $questions)
    {
        $questions = $this->normalizeQuestions($questions);

        $this->preprocessQuestions($questions);

        $vars = parent::collectVars($input, $output, $questions);

        if (empty($input->getOption('directory'))) {
            $this->setDirectory($vars);
        }

        return $vars;
    }

    /**
     * Modifies questions for better DX.
     *
     * @param \Symfony\Component\Console\Question\Question[] $questions
     *   List of questions to modify.
     *
     * @todo Shall we add validation callbacks for names?
     */
    protected function preprocessQuestions(array &$questions)
    {

        if (isset($questions['name'])) {
            // @todo Pick up default name from current working directory when possible.
            $this->setQuestionDefault($questions['name'], '');
        }

        if (!isset($questions['machine_name'])) {
            return;
        }

        /** @var \DrupalCodeGenerator\Command\GeneratorInterface $command */
        $command = $this->getHelperSet()->getCommand();
        $destination = $command->getDestination();

        // Module related generators.
        if ($destination == 'modules/%') {
            $modules = [];
            $moduleHandler = \Drupal::moduleHandler();
            foreach ($moduleHandler->getModuleList() as $machine_name => $module) {
                $modules[$machine_name] = $moduleHandler->getName($machine_name);
            }

            $questions['machine_name']->setAutocompleterValues(array_keys($modules));

            if (isset($questions['name'])) {
                $questions['name']->setAutocompleterValues($modules);
                $questions['name']->setNormalizer([$this, 'machineToLabel']);
                $default_machine_name = function ($vars) use ($modules) {
                    $machine_name = array_search($vars['name'], $modules);
                    return $machine_name ?: Utils::human2machine($vars['name']);
                };
                $this->setQuestionDefault($questions['machine_name'], $default_machine_name);
            } else {
                // Only machine name exists.
                $this->setQuestionDefault($questions['machine_name'], 'example');
            }
        // Theme related generators.
        } elseif ($destination == 'themes/%') {
            $themes = [];
            foreach (\Drupal::service('theme_handler')->listInfo() as $machine_name => $theme) {
                $themes[$machine_name] = $theme->info['name'];
            }
            $questions['machine_name']->setAutocompleterValues(array_keys($themes));
            if (isset($questions['name'])) {
                $questions['name']->setAutocompleterValues(array_values($themes));
                $default_machine_name = function ($vars) use ($themes) {
                    $machine_name = array_search($vars['name'], $themes);
                    return $machine_name ?: Utils::human2machine($vars['name']);
                };
                $this->setQuestionDefault($questions['machine_name'], $default_machine_name);
            }
        }
    }

    /**
     * Defines the directory where generated files should be dumped.
     *
     * @param array $vars
     *   Collected variables.
     */
    protected function setDirectory(array $vars) {
        /** @var \DrupalCodeGenerator\Command\GeneratorInterface $command */
        $command = $this->getHelperSet()->getCommand();
        $destination = $command->getDestination();

        // Check if the generator can handle it itself.
        if (is_callable($destination)) {
            $directory = $destination($vars);
        } else {
            $modules_dir = is_dir(DRUPAL_ROOT . '/modules/custom') ?
              'modules/custom' : 'modules';

            $directory = false;
            switch ($destination) {
                case 'modules':
                    $directory = $modules_dir;
                    break;

                case 'themes':
                    $directory = 'themes';
                    break;

                case 'modules/%':
                    if (isset($vars['machine_name'])) {
                        $machine_name = $vars['machine_name'];
                        $modules = \Drupal::moduleHandler()->getModuleList();
                        $directory = isset($modules[$machine_name])
                          ? $modules[$machine_name]->getPath()
                          : $modules_dir . '/' . $machine_name;
                    }
                    break;

                case 'themes/%':
                    if (isset($vars['machine_name'])) {
                        $machine_name = $vars['machine_name'];
                        $themes = \Drupal::service('theme_handler')->listInfo();
                        $directory = isset($themes[$machine_name])
                          ? $themes[$machine_name]->getPath()
                          : 'themes/' . $machine_name;
                    }
                    break;

                case 'profiles':
                    $directory = 'profiles';
                    break;

                case 'sites/default':
                    $directory = 'sites/default';
                    break;
            }
        }

        /** @var \DrupalCodeGenerator\Command\GeneratorInterface $command */
        $directory && $command->setDirectory($directory);
    }

    /**
     * Returns module name by its machine name.
     *
     * @param string $machine_name
     *   Module machine name.
     *
     * @return string
     *   Module name.
     */
    public function machineToLabel($machine_name)
    {
        $handler = \Drupal::moduleHandler();
        if ($handler->moduleExists($machine_name)) {
            return $handler->getName($machine_name);
        }
        return $machine_name;
    }
}
