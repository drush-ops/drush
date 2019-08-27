<?php

namespace Drush\Commands\generate\Generators\Drush;

use DrupalCodeGenerator\Command\BaseGenerator;
use Drush\Drush;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Implements drush-alias-file command.
 */
class DrushAliasFile extends BaseGenerator
{

    protected $name = 'drush-alias-file';
    protected $description = 'Generates a Drush site alias file.';
    protected $alias = 'daf';
    protected $templatePath = __DIR__;

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questions['prefix'] = new Question('File prefix (one word)', 'self');
        $questions['root'] = new Question('Path to Drupal root', Drush::bootstrapManager()->getRoot());
        $questions['uri'] = new Question('Drupal uri', Drush::bootstrapManager()->getUri());
        $questions['host'] = new Question('Remote host');
        $vars = $this->collectVars($input, $output, $questions);

        if ($vars['host']) {
            $remote_questions['user'] = new Question('Remote user', Drush::config()->user());
            $this->collectVars($input, $output, $remote_questions);
        }

        $this->addFile()
            ->path('drush/{prefix}.site.yml')
            ->template('drush-alias-file.yml.twig');
    }
}
