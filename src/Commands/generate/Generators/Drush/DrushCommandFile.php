<?php

declare(strict_types=1);

namespace Drush\Commands\generate\Generators\Drush;

use Consolidation\OutputFormatters\FormatterManager;
use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use Drush\Log\DrushLoggerManager;
use Drush\Runtime\DependencyInjection;

#[Generator(
    name: 'drush:command-file',
    description: 'Generates a Drush command file.',
    aliases: ['dcf'],
    templatePath: __DIR__,
    type: GeneratorType::MODULE_COMPONENT,
)]
class DrushCommandFile extends BaseGenerator
{
    /**
     * {@inheritdoc}
     */
    protected function generate(array &$vars, Assets $assets): void
    {
        $ir = $this->createInterviewer($vars);
        $vars['machine_name'] = $ir->askMachineName();
        $vars['name'] = $ir->askName();

        $vars['class'] = $ir->askClass(default: '{machine_name|camelize}Command');
        $vars['services'] = $ir->askServices(false, ['token']);
        $vars['services']['logger'] = [
            'name' => 'logger',
            'type' => 'DrushLoggerManager',
            'type_fqn' => DrushLoggerManager::class,
        ];
        $vars['services'][DependencyInjection::FORMATTER_MANAGER] = [
            'name' => DependencyInjection::FORMATTER_MANAGER,
            'type' => 'FormatterManager',
            'type_fqn' => FormatterManager::class,
        ];

        $assets->addFile('src/Drush/Commands/{class}.php', 'drush-command-file.php.twig');
    }
}
