<?php

namespace Drush\Commands\generate\Helpers;

use Symfony\Component\Console\Helper\Helper;
use DrupalCodeGenerator\Utils;

/**
 * Input preprocessor for code generators.
 */
class InputPreprocessor extends Helper {

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'dcg_input_preprocessor';
  }

  /**
   * Modifies default DCG questions for better DX.
   *
   * @param array $questions
   *   List of questions to modify.
   *
   * @todo Shall we add validation callbacks for names?
   */
  public function preprocess(array &$questions) {

    if (isset($questions['name'])) {
      // @todo Pick up default name from current working directory when possible.
      $questions['name'][1] = FALSE;
    }

    /** @var \Symfony\Component\Console\Command\Command $command */
    $command = $this->getHelperSet()->getCommand();
    $command_name = $command->getName();

    $excluded = [
      'module-configuration-entity',
      'module-content-entity',
      'module-plugin-manager',
      'module-standard',
      'theme-standard',
      'settings-local',
      'yml-theme-info',
      'yml-module-info',
    ];
    if (in_array($command_name, $excluded)) {
      return;
    }

    // Theme related generators (only one so far).
    if ($command_name == 'theme-file') {
      $themes = [];
      foreach (\Drupal::service('theme_handler')->listInfo() as $machine_name => $theme) {
        $themes[$machine_name] = $theme->info['name'];
      }
      $questions['name'][3] = array_values($themes);
      $questions['machine_name'][1] = function ($vars) use ($themes) {
        $machine_name = array_search($vars['name'], $themes);
        return $machine_name ?: Utils::human2machine($vars['name']);
      };
      $questions['machine_name'][3] = array_keys($themes);

      return;
    }

    // Module related generators.
    if (isset($questions['machine_name'])) {
      $modules = [];
      // system_rebuild_module_data() seems to be redundant because we do not
      // want to rebuild anything. We just need cached module definitions.
      foreach (system_rebuild_module_data() as $machine_name => $module) {
        $modules[$machine_name] = $module->info['name'];
      }

      $questions['machine_name'][3] = array_keys($modules);

      if (isset($questions['name'])) {
        $questions['name'][3] = array_values($modules);
        $questions['machine_name'][1] = function ($vars) use ($modules) {
          $machine_name = array_search($vars['name'], $modules);
          return $machine_name ?: Utils::human2machine($vars['name']);
        };
      }
      // Only machine name exists.
      else {
        $questions['machine_name'][1] = 'example';
      }
    }

  }

}
