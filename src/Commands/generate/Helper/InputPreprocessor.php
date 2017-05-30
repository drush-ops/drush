<?php

namespace Drush\Commands\generate\Helper;

use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Helper\Helper;

/**
 * Input preprocessor for code generators.
 */
class InputPreprocessor extends Helper
{

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'dcg_input_preprocessor';
    }

    /**
     * Modifies default DCG questions for better DX.
     *
     * @param array $questions
     *   List of questions to modify.
     *
     * @todo Shall we add validation callbacks for names?
     */
    public function preprocess(array &$questions)
    {

        if (isset($questions['name'])) {
            // @todo Pick up default name from current working directory when possible.
            $questions['name'][1] = false;
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
            // @todo - For better UX, match on both labels and machine names.
            $moduleHandler = \Drupal::moduleHandler();
            foreach ($moduleHandler->getModuleList() as $machine_name => $module) {
                $modules[$machine_name] = $moduleHandler->getName($machine_name);
            }

            $questions['machine_name'][3] = array_keys($modules);

            if (isset($questions['name'])) {
                $questions['name'][3] = array_values($modules);
                $questions['machine_name'][1] = function ($vars) use ($modules) {
                    $machine_name = array_search($vars['name'], $modules);
                    return $machine_name ?: Utils::human2machine($vars['name']);
                };
            } else {
                // Only machine name exists.
                $questions['machine_name'][1] = 'example';
            }
        // Theme related generators.
        } elseif ($destination == 'themes/%') {
            $themes = [];
            foreach (\Drupal::service('theme_handler')->listInfo() as $machine_name => $theme) {
                $themes[$machine_name] = $theme->info['name'];
            }
            $questions['name'][3] = array_values($themes);
            $questions['machine_name'][1] = function ($vars) use ($themes) {
                $machine_name = array_search($vars['name'], $themes);
                return $machine_name ?: Utils::human2machine($vars['name']);
            };
            $questions['machine_name'][3] = array_keys($themes);
        }
    }
}
