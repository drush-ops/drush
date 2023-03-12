<?php

declare(strict_types=1);

namespace Drush\Commands\generate\Generators\Drush;

use DrupalCodeGenerator\Command\ModuleGenerator;
use DrupalCodeGenerator\Utils;
use Drush\Drush;

/**
 * Implements drush-command-file command.
 */
class DrushCommandFile extends ModuleGenerator
{
    protected string $name = 'drush:command-file';
    protected string $description = 'Generates a Drush command file.';
    protected string $alias = 'dcf';
    protected string $templatePath = __DIR__;

    /**
     * {@inheritdoc}
     */
    protected function generate(array &$vars): void
    {
        $this->collectDefault($vars);

        $validator = static function ($path) {
            if ($path && !is_file($path)) {
                throw new \UnexpectedValueException(sprintf('Could not open file "%s".', $path));
            }
            return $path;
        };
        $vars['class'] = '{machine_name|camelize}Commands';

        $this->addFile('src/Commands/{class}.php', 'drush-command-file.php');

        $json = $this->getComposerJson($vars);
        $content = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->addFile('composer.json')
            ->content($content)
            ->replaceIfExists();

        $this->addFile('drush.services.yml', 'drush.services.yml');
    }

    protected function getComposerJson(array $vars): array
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

        // Add an entry for the Drush services file.
        $composer_json_data['extra']['drush']['services'] = [
            'drush.services.yml' => '^' . Drush::getMajorVersion(),
        ];

        return $composer_json_data;
    }

    protected function getOwningModulePath(array $vars): string
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
}
