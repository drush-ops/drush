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
    public function collectVars(InputInterface $input, OutputInterface $output, array $questions, array $vars = [])
    {
        $questions = $this->normalizeQuestions($questions);

        /** @var \DrupalCodeGenerator\Command\GeneratorInterface $command */
        $command = $this->getHelperSet()->getCommand();
        $destination = $command->getDestination();

        $this->preprocessQuestions($questions, $destination);

        $existing_extension = in_array($destination, ['modules/%', 'themes/%']);

        $vars = [];

        // If both name and machine_name questions are defined it is quite
        // possible that we can provide the extension name without interacting
        // with a user.
        if (isset($questions['name'], $questions['machine_name']) && $existing_extension) {
            // Collect only machine_name answer.
            $vars += parent::collectVars($input, $output, ['machine_name' => $questions['machine_name']]);
            unset($questions['machine_name']);

            if ($destination == 'modules/%') {
                $moduleHandler = \Drupal::moduleHandler();
                if ($moduleHandler->moduleExists($vars['machine_name'])) {
                    $vars['name'] = $moduleHandler->getName($vars['machine_name']);
                    unset($questions['name']);
                }
            }
            elseif ($destination == 'themes/%') {
                $themeHandler = \Drupal::service('theme_handler');
                if ($themeHandler->themeExists($vars['machine_name'])) {
                    $vars['name'] = $themeHandler->getName($vars['machine_name']);
                    unset($questions['name']);
                }
            }

            // If an extension with provided machine name was not found the name
            // question is still actual. So we can set default value for it.
            if (isset($questions['name']) && !$questions['name']->getDefault()) {
                $this->setQuestionDefault($questions['name'], function ($vars) {
                    return Utils::machine2human($vars['machine_name']);
                });
            }
        }

        // Collect all other variables.
        $vars += parent::collectVars($input, $output, $questions, $vars);

        // Set an appropriate directory for dumped files.
        if (empty($input->getOption('directory')) && ($directory = $this->getDirectory($vars, $destination))) {
            $command->setDirectory($directory);
        }

        return $vars;
    }

    /**
     * Modifies questions for better DX.
     *
     * @param \Symfony\Component\Console\Question\Question[] $questions
     *   List of questions to modify.
     * @param string $destination
     *   The destination for dumped files.
     *
     * @todo Shall we add validation callbacks for names?
     */
    protected function preprocessQuestions(array &$questions, $destination)
    {

        if (isset($questions['name'])) {
            // @todo Pick up default name from current working directory when possible.
            $this->setQuestionDefault($questions['name'], '');
        }

        if (!isset($questions['machine_name'])) {
            return;
        }

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
     * @param string $destination
     *   The destination for dumped files.
     */
    protected function getDirectory(array $vars, $destination) {

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

        return $directory;
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
