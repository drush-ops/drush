<?php

namespace Drush\Commands\generate\Helper;

use DrupalCodeGenerator\Helper\QuestionSettersTrait;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Helper\Helper;

/**
 * Input preprocessor for code generators.
 */
class InputPreprocessor extends Helper
{

    use QuestionSettersTrait;

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'dcg_input_preprocessor';
    }

    /**
     * Modifies questions for better DX.
     *
     * @param \Symfony\Component\Console\Question\Question[] $questions
     *   List of questions to modify.
     *
     * @todo Shall we add validation callbacks for names?
     */
    public function preprocess(array &$questions)
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

    public function machineToLabel($choice)
    {
        $handler = \Drupal::moduleHandler();
        if ($handler->moduleExists($choice)) {
            return $handler->getName($choice);
        }
        return $choice;
    }
}
