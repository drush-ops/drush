<?php

declare(strict_types=1);

namespace Drush\Commands\generate\Generators\Drush;

use Consolidation\OutputFormatters\FormatterManager;
use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use DrupalCodeGenerator\Utils;
use DrupalCodeGenerator\Validator\RegExp;
use Drush\Runtime\DependencyInjection;
use Psr\Log\LoggerInterface;

#[Generator(
    name: 'drush:command',
    description: 'Generates a Drush command.',
    aliases: ['drush:command-file', 'dcf'],
    templatePath: __DIR__,
    type: GeneratorType::MODULE_COMPONENT,
)]
class DrushCommandFile extends BaseGenerator
{
    protected function generate(array &$vars, Assets $assets): void
    {
        $ir = $this->createInterviewer($vars);
        // Module
        $vars['machine_name'] = $ir->askMachineName();
        $vars['name'] = $ir->askName();

        $command_name_validator = new RegExp('/^[a-z][a-z0-9-_:]*[a-z0-9]$/', 'The value is not correct command name.');
        $vars['command']['name'] = $ir->ask('Command name', '{machine_name}:example', $command_name_validator);

        $vars['command']['description'] = $ir->ask('Command description');

        $sub_names = \explode(':', $vars['command']['name']);
        $short_name = \array_pop($sub_names);

        $alias_validator = new RegExp('/^[a-z0-9_-]+$/', 'The value is not correct alias name.');
        $vars['command']['alias'] = $ir->ask('Command alias', $short_name, $alias_validator);

//        $vars['command_suffix'] = $ir->ask('Command suffix (e.g. for devel:hook, the suffix is \'hook\')', null, new Required());
//        $vars['description'] = $ir->ask('Description');

        // Command name

        $vars['class'] = $ir->askClass('Class', Utils::camelize($short_name) . 'Command');
        $vars['services'] = $ir->askServices(false, ['token']);
        $vars['services']['logger'] = [
            'name' => 'logger',
            'type' => 'LoggerInterface',
            'type_fqn' => LoggerInterface::class,
        ];
        $vars['services'][DependencyInjection::FORMATTER_MANAGER] = [
            'name' => DependencyInjection::FORMATTER_MANAGER,
            'type' => 'FormatterManager',
            'type_fqn' => FormatterManager::class,
        ];

        $assets->addFile('src/Drush/Commands/{class}.php', 'drush-command-file.php.twig');
    }
}
