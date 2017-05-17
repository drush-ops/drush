<?php

namespace Drush\Generate;

use DrupalCodeGenerator\Commands\BaseGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\PathUtil\Path;

class DrushGenerator extends BaseGenerator
{

  // Values are replaced dynamically. See \Drush\Boot\DrupalBoot8::bootstrapDrupalFull.
  // Values are required by the Command constructor.
  protected $name = 'temp';
  protected $description = 'temp';
  public $generator = null;

  // See interact().
  protected $files;
  protected $destination;

  public static function create(array $twig_directories)
  {
    return parent::create($twig_directories);
  }

  protected function interact(InputInterface $input, OutputInterface $output)
  {
    if (empty($input->getOption('answers'))) {
      $moduleHandler = \Drupal::moduleHandler();
      $list = $moduleHandler->getModuleList();
      $names = array_keys($list);
      $module = $this->ask($input, $output, dt('Pick an enabled module.'), '', array_combine($names, $names));
      $answers = json_encode(['name' => $module, 'machine_name' => $module]);
      $input->setOption('answers', $answers);
      // Used by execute() to save generated files.
      $destination = Path::makeAbsolute($list[$module]->getPath(), DRUPAL_ROOT);
      $input->setOption('destination', $destination);
      $this->destination = $destination;
    }
    $this->generator->setApplication($this->getApplication());
    $this->generator->interact($input, $output);
    $this->files = $this->generator->files;
    $this->hooks = $this->generator->hooks ?: [];
    $this->services = $this->generator->services ?: null;
  }


}