<?php

namespace Drush\Commands\generate\Generators\Drush;

use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        $questions = Utils::defaultQuestions() + [
            'source' => ['Absolute path to legacy Drush commandfile (optional - for porting)'],
        ];

        $vars = $this->collectVars($input, $output, $questions);
        $vars['class'] = Utils::camelize($vars['machine_name'] . 'Commands');
        if ($vars['source']) {
            require_once $vars['source'];
            $filename = str_replace(['.drush.inc', '.drush8.inc'], '', basename($vars['source']));
            $commands = call_user_func($filename . '_drush_command');
            $vars['commands'] = $this->adjustCommands($commands);
        }
        $this->files['src/Commands/' . $vars['class'] . '.php'] = $this->render('drush-command-file.twig', $vars);
        $this->services[$vars['machine_name'] . '.commands'] = [
            'class' => '\Drupal\\' . $vars['machine_name'] . '\Commands\\' . $vars['class'],
            'tags' => [
                [
                    'name' => 'drush.command',
                ],
            ],
        ];
    }

    protected function adjustCommands($commands) {
        foreach ($commands as $name => &$command) {
            $command['method'] = $name;
            if (($pos = strpos($name, '-')) !== FALSE) {
                $command['method'] = substr($name, $pos + 1);
            }
            $command['method'] = Utils::camelize($command['method'], false);
            if ($command['options']) {
                foreach ($command['options'] as $oName => &$option) {
                    // We only care about option description so make value a simple string.
                    if (is_array($option)) {
                        $option = $option['description'];
                    }
                    $oNames[] = "'$oName' => null";
                }
                $command['optionsConcat'] = '$options = [' . implode(', ', $oNames) . ']';
                unset($oNames);
            }
            if ($command['arguments']) {
                foreach ($command['arguments'] as $aName => $desciption) {
                    // Prepend name with a '$'.
                    $command['arguments']['$' . $aName] = $desciption;
                    unset($command['arguments'][$aName]);
                }
                if ($concat = implode(', ', array_keys($command['arguments']))) {
                    $command['argumentsConcat'] = $concat . ', ';
                }
            }
        }
        return $commands;
    }
}
