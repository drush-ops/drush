<?php

namespace Drush\Commands\generate\Generators\Drush;

use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Implements drush-port command.
 */
class DrushPort extends BaseGenerator
{

    protected $name = 'drush-port';
    protected $description = 'Generates a Drush command file based on a Drush8 commandfile.';
    protected $alias = 'dport';
    protected $templatePath = __DIR__;

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        // Utils::defaultQuestions() +
        $questions = [
            'source' => ['Path to source file', '/Users/moshe.weitzman/reps/watchers/web/modules/contrib/features/drush/features.drush8.inc'], // /tmp/example.drush.php
        ];

        $vars = $this->collectVars($input, $output, $questions);
        require_once $vars['source'];
        $filename = str_replace(['.drush.inc', '.drush8.inc'], '', basename($vars['source']));
        $vars['commands'] = call_user_func($filename . '_drush_command');
        $generated = $this->render('drush-port.twig', $vars);
        // Just write out results since they have to be pasted into a class anyway.
        drush_print($generated);
        // $this->files['src/Commands/' . $vars['class'] . '.php'] = $this->render('drush-command-file.twig', $vars);
    }
}
