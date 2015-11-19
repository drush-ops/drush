<?php

use Drupal\Component\Assertion\Handle;
use Drush\Psysh\DrushHelpCommand;
use Drush\Psysh\DrushCommand;
use Drush\Psysh\Shell;

/**
 * Implements hook_drush_command().
 */
function cli_drush_command() {
  $items['core-cli'] = array(
    'description' => 'Open an interactive shell on a Drupal site.',
    'remote-tty' => TRUE,
    'aliases' => array('php'),
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'topics' => array('docs-repl'),
  );
  $items['docs-repl'] = array(
    'description' => 'repl.md',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array(drush_get_context('DOC_PREFIX', DRUSH_BASE_PATH) . '/docs/repl.md'),
  );
  return $items;
}

/**
 * Command callback.
 */
function drush_cli_core_cli() {
  $configuration = new \Psy\Configuration();
  $shell = new Shell($configuration);

  if (drush_drupal_major_version() >= 8) {
    // Register the assertion handler so exceptions are thrown instead of errors
    // being triggered. This plays nicer with PsySH.
    Handle::register();
    $shell->setScopeVariables(['container' => \Drupal::getContainer()]);

    // Add Drupal 8 specific casters to the shell configuration.
    $configuration->addCasters(_drush_core_cli_get_casters());
  }

  // Add Drush commands to the shell.
  $commands = [new DrushHelpCommand()];

  foreach (drush_commands_categorize(_drush_core_cli_get_commands()) as $category_data) {
    $category_title = (string) $category_data['title'];
    foreach ($category_data['commands'] as $command_config) {
      $command = new DrushCommand($command_config);
      // Set the category label on each.
      $command->setCategory($category_title);
      $commands[] = $command;
    }
  }

  $shell->addCommands($commands);

  // PsySH will never return control to us, but our shutdown handler will still
  // run after the user presses ^D.  Mark this command as completed to avoid a
  // spurious error message.
  drush_set_context('DRUSH_EXECUTION_COMPLETED', TRUE);

  // Run the terminate event before the shell is run. Otherwise, if the shell
  // is forking processes (the default), any child processes will close the
  // database connection when they are closed. So when we return back to the
  // parent process after, there is no connection. Call terminate() regardless,
  // this is a no-op for all DrupalBoot classes except DrupalBoot8. This will be
  // called after the command in preflight still, but the subscriber instances
  // are already created from before.
  if ($bootstrap = drush_get_bootstrap_object()) {
    $bootstrap->terminate();
  }

  $shell->run();
}

/**
 * Returns a filtered list of Drush commands used for CLI commands.
 *
 * @return array
 */
function _drush_core_cli_get_commands() {
  $commands = drush_get_commands();
  $ignored_commands = ['help', 'drush-psysh', 'php-eval', 'core-cli', 'php'];
  $php_keywords = _drush_core_cli_get_php_keywords();

  foreach ($commands as $name => $config) {
    // Ignore some commands that don't make sense inside PsySH, are PHP keywords
    // are hidden, or are aliases.
    if (in_array($name, $ignored_commands) || in_array($name, $php_keywords) || !empty($config['hidden']) || ($name !== $config['command'])) {
      unset($commands[$name]);
    }
    else {
      // Make sure the command aliases don't contain any PHP keywords.
      if (!empty($config['aliases'])) {
        $commands[$name]['aliases'] = array_diff($commands[$name]['aliases'], $php_keywords);
      }
    }
  }

  return $commands;
}

/**
 * Returns a mapped array of casters for use in the shell.
 *
 * These are Symfony VarDumper casters.
 * See http://symfony.com/doc/current/components/var_dumper/advanced.html#casters
 * for more information.
 *
 * @return array.
 *   An array of caster callbacks keyed by class or interface.
 */
function _drush_core_cli_get_casters() {
  return [
    'Drupal\Core\Entity\ContentEntityInterface' => function($entity, $array, $stub, $isNested) {
      if (!$isNested) {
        foreach ($entity as $property => $item) {
          $array[Symfony\Component\VarDumper\Caster\Caster::PREFIX_PROTECTED . $property] = $item;
        }
      }

      return $array;
    },
    'Drupal\Core\Field\FieldItemListInterface' => function($list_item, $array, $stub, $isNested) {
      if (!$isNested) {
        foreach ($list_item as $delta => $item) {
          $array[Symfony\Component\VarDumper\Caster\Caster::PREFIX_VIRTUAL . $delta] = $item;
        }
      }

      return $array;
    },
    'Drupal\Core\Field\FieldItemInterface' => function($item, $array, $stub, $isNested) {
      if (!$isNested) {
        $array[Symfony\Component\VarDumper\Caster\Caster::PREFIX_VIRTUAL . 'value'] = $item->getValue();
      }

      return $array;
    },
    'Drupal\Core\Config\Entity\ConfigEntityInterface' => function($entity, $array, $stub, $isNested) {
      if (!$isNested) {
        foreach ($entity->toArray() as $property => $value) {
          $array[Symfony\Component\VarDumper\Caster\Caster::PREFIX_PROTECTED . $property] = $value;
        }
      }

      return $array;
    },
    'Drupal\Core\Config\ConfigBase' => function($config, $array, $stub, $isNested) {
      if (!$isNested) {
        foreach ($config->get() as $property => $value) {
          $array[Symfony\Component\VarDumper\Caster\Caster::PREFIX_VIRTUAL . $property] = $value;
        }
      }

      return $array;
    },
  ];
}

/**
 * Returns a list of PHP keywords.
 *
 * This will act as a blacklist for command and alias names.
 *
 * @return array
 */
function _drush_core_cli_get_php_keywords() {
  return [
    '__halt_compiler',
    'abstract',
    'and',
    'array',
    'as',
    'break',
    'callable',
    'case',
    'catch',
    'class',
    'clone',
    'const',
    'continue',
    'declare',
    'default',
    'die',
    'do',
    'echo',
    'else',
    'elseif',
    'empty',
    'enddeclare',
    'endfor',
    'endforeach',
    'endif',
    'endswitch',
    'endwhile',
    'eval',
    'exit',
    'extends',
    'final',
    'for',
    'foreach',
    'function',
    'global',
    'goto',
    'if',
    'implements',
    'include',
    'include_once',
    'instanceof',
    'insteadof',
    'interface',
    'isset',
    'list',
    'namespace',
    'new',
    'or',
    'print',
    'private',
    'protected',
    'public',
    'require',
    'require_once',
    'return',
    'static',
    'switch',
    'throw',
    'trait',
    'try',
    'unset',
    'use',
    'var',
    'while',
    'xor',
  ];
}
