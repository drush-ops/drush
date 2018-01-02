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
        $questions['source']->setValidator(function ($path) {
            if ($path && !is_file($path)) {
                throw new \UnexpectedValueException(sprintf('Could not open file "%s".', $path));
            }
            return $path;
        });

        $vars = &$this->collectVars($input, $output, $questions);
        $vars['class'] = Utils::camelize($vars['machine_name'] . 'Commands');
        if ($vars['source']) {
            require_once $vars['source'];
            $filename = str_replace(['.drush.inc', '.drush8.inc'], '', basename($vars['source']));
            $command_hook = $filename . '_drush_command';
            if (!function_exists($command_hook)) {
                throw new \InvalidArgumentException('Drush command hook "' . $command_hook . '" does not exist.');
            }
            $commands = call_user_func($filename . '_drush_command');
            $vars['commands'] = $this->adjustCommands($commands);
        }

        $this->addFile()
            ->path('src/Commands/{class}.php')
            ->template('drush-command-file.twig');

        $json = $this->getComposerJson($vars);
        $content = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->addFile()
            ->path('composer.json')
            ->content($content)
            ->action('replace');

        $this->addFile()
            ->path('drush.services.yml')
            ->template('drush.services.twig');
    }

    protected function getComposerJson(array $vars)
    {
        $composer_json_template_path = __DIR__ . '/dcf-composer.json';
        // TODO: look up the path of the 'machine_name' module.
        $composer_json_existing_path = DRUPAL_ROOT . '/modules/' . $vars['machine_name'] . '/composer.json';
        $composer_json_path = file_exists($composer_json_existing_path) ? $composer_json_existing_path : $composer_json_template_path;
        $composer_json_contents = file_get_contents($composer_json_path);
        $composer_json_data = json_decode($composer_json_contents, true);

        // If there is no name, fill something in
        if (empty($composer_json_data['name'])) {
            $composer_json_data['name'] = 'org/' . $vars['machine_name'];
        }

        // Add an entry for the drush services file.
        $composer_json_data['extra']['drush']['services'] = [
            'drush.services.yml' => '^9',
        ];

        return $composer_json_data;
    }

    protected function getOwningModulePath(array $vars)
    {
        $module_name = $vars['machine_name'];

        $modules = \Drupal::moduleHandler()->getModuleList();
        $themes = \Drupal::service('theme_handler')->listInfo();
        $projects = array_merge($modules, $themes);

        if (!isset($projects[$module_name])) {
             throw new \Exception(dt('{module} does not exist. Run `drush generate module-standard` to create it.', ['module' => $module_name]));
        }
        return $projects[$module_name]->getPath();
    }

    protected function adjustCommands(array $commands)
    {
        foreach ($commands as $name => &$command) {
            // Drush9 uses colons in command names. Replace first dash with colon.
            $pos = strpos($name, '-');
            if ($pos !== false) {
                $command['name'] = substr_replace($name, ':', $pos, 1);
            }

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
                    $command['arguments']['$' . Utils::human2machine($aName)] = $description;
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
