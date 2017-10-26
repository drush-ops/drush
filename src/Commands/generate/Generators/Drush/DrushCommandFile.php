<?php

namespace Drush\Commands\generate\Generators\Drush;

use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Implements drush-command-file command.
 */
class DrushCommandFile extends BaseGenerator
{

    protected $name = 'drush-command-file';
    protected $description = 'Generates a Drush command file.';
    protected $alias = 'dcf';
    protected $templatePath = __DIR__;

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questions = Utils::defaultQuestions();
        $questions['source'] = new Question('Absolute path to legacy Drush command file (optional - for porting)');

        $vars = $this->collectVars($input, $output, $questions);
        $vars['class'] = Utils::camelize($vars['machine_name'] . 'Commands');
        if ($vars['source']) {
            require_once $vars['source'];
            $filename = str_replace(['.drush.inc', '.drush8.inc'], '', basename($vars['source']));
            $commands = call_user_func($filename . '_drush_command');
            $vars['commands'] = $this->adjustCommands($commands);
        }
        $this->setFile('src/Commands/' . $vars['class'] . '.php', 'drush-command-file.twig', $vars);
        $this->setServicesFile('drush.services.yml', 'drush.services.twig', $vars);
    }

    protected function adjustCommands($commands)
    {
        foreach ($commands as $name => &$command) {
            // Drush9 uses colons in command names.
            $command['name'] = str_replace('-', ':', $name);
            if ($command['name'] !== $name) {
                $command['aliases'][] = $name;
            }

            $command['method'] = $name;
            if (($pos = strpos($name, '-')) !== false) {
                $command['method'] = substr($name, $pos + 1);
            }
            $command['method'] = Utils::camelize(str_replace('-', '_', $command['method']), false);
            if ($command['arguments']) {
                foreach ($command['arguments'] as $aName => $description) {
                    // Prepend name with a '$' and replace dashes.
                    $command['arguments']['$' . Utils::camelize(str_replace('-', '_', $aName))] = $description;
                    unset($command['arguments'][$aName]);
                }
                $command['argumentsConcat'] = implode(', ', array_keys($command['arguments']));
            }
            if ($command['options']) {
                foreach ($command['options'] as $oName => &$option) {
                    // We only care about option description so make value a simple string.
                    if (is_array($option)) {
                        $option = $option['description'];
                    }
                    $oNames[] = "'$oName' => null";
                }
                $command['optionsConcat'] = 'array $options = [' . implode(', ', $oNames) . ']';
                if (!empty($command['arguments'])) {
                    $command['optionsConcat'] = ', ' . $command['optionsConcat'];
                }
                unset($oNames);
            }
            if ($deps = $command['drupal dependencies']) {
                $command['depsConcat'] = implode(',', $deps);
            }
        }
        return $commands;
    }
}
