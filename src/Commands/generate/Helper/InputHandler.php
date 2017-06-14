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
            /** @var \DrupalCodeGenerator\Command\GeneratorInterface $command */
            $command = $this->getHelperSet()->getCommand();
            $destination = $command->getDestination();

            // Check if the generator can handle it itself.
            if (is_callable($destination)) {
                $directory = $destination($vars);
            } else {
                $modules_dir = is_dir(DRUPAL_ROOT . '/modules/custom') ?
                  'modules/custom' : 'modules';

                $directory = false;
                switch ($destination) {
                    case 'modules':
                        $directory = $modules_dir;
                        break;

                    case 'themes':
                        $directory = 'themes';
                        break;

                    case 'modules/%':
                        if (isset($vars['machine_name'])) {
                            $machine_name = $vars['machine_name'];
                            $modules = \Drupal::moduleHandler()->getModuleList();
                            $directory = isset($modules[$machine_name])
                              ? $modules[$machine_name]->getPath()
                              : $modules_dir . '/' . $machine_name;
                        }
                        break;

                    case 'themes/%':
                        if (isset($vars['machine_name'])) {
                            $machine_name = $vars['machine_name'];
                            $themes = \Drupal::service('theme_handler')->listInfo();
                            $directory = isset($themes[$machine_name])
                                ? $themes[$machine_name]->getPath()
                                : 'themes/' . $machine_name;
                        }
                        break;

                    case 'profiles':
                        $directory = 'profiles';
                        break;

                    case 'sites/default':
                        $directory = 'sites/default';
                        break;
                }
            }

            /** @var \DrupalCodeGenerator\Command\GeneratorInterface $command */
            $directory && $command->setDirectory($directory);
        }
        return $vars;
    }
}
