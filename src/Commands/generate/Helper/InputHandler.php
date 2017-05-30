<?php

namespace Drush\Commands\generate\Helper;

use DrupalCodeGenerator\Helper\InputHandler as BaseInputHandler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generators input handler.
 */
class InputHandler extends BaseInputHandler
{

    /**
     * {@inheritdoc}
     */
    public function collectVars(InputInterface $input, OutputInterface $output, array $questions)
    {
        $vars = parent::collectVars($input, $output, $questions);
        if (empty($input->getOption('directory'))) {
            /** @var \Symfony\Component\Console\Command\Command $command */
            $command = $this->getHelperSet()->getCommand();

            $modules_dir = is_dir(DRUPAL_ROOT . '/modules/custom') ?
                'modules/custom' : 'modules';

            $directory = false;
            switch ($command->getName()) {
                case 'module-configuration-entity':
                case 'module-content-entity':
                case 'module-plugin-manager':
                case 'module-standard':
                    $directory = $modules_dir;
                    break;
                case 'theme-standard':
                    $directory = 'themes';
                    break;

                case 'theme-file':
                    // @todo Handle this case.
                    break;

                case 'settings-local':
                    $directory = 'sites/default';
                    break;

                case 'yml-theme-info':
                    // Do nothing.
                    break;

                case 'yml-module-info':
                    // @todo Handle this case.
                    break;

                default:
                    if (isset($vars['machine_name'])) {
                        $machine_name = $vars['machine_name'];
                        // @todo Can we proccess disabled modules as well?
                        $modules = system_rebuild_module_data();
                        $directory = isset($modules[$machine_name])
                            ? $modules[$machine_name]->getPath()
                            : $modules_dir . '/' . $machine_name;
                    } else {
                        // @todo Handle this case.
                    }
            }
            /** @var \DrupalCodeGenerator\Command\GeneratorInterface $command */
            $directory && $command->setDirectory($directory);
        }
        return $vars;
    }
}
