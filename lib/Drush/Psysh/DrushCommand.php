<?php
/**
 * @file
 * Contains \Drush\Psysh\DrushCommand.
 *
 * DrushCommand is a PsySH proxy command which accepts a Drush command config
 * array and tries to build an appropriate PsySH command for it.
 */

namespace Drush\Psysh;

use Psy\Command\Command as BaseCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Main Drush command.
 */
class DrushCommand extends BaseCommand {

  /**
   * @var array
   */
  private $config;

  /**
   * @var string
   */
  private $category = '';

  /**
   * DrushCommand constructor.
   *
   * This accepts the Drush command configuration array and does a pretty
   * decent job of building a PsySH command proxy for it. Wheee!
   *
   * @param array $config
   *   Drush command configuration array.
   */
  public function __construct(array $config) {
    $this->config = $config;
    parent::__construct();
  }

  /**
   * Get Category of this command.
   */
  public function getCategory() {
    return $this->category;
  }

  /**
   * Sets the category title.
   *
   * @param string $category_title
   */
  public function setCategory($category_title) {
    $this->category = $category_title;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName($this->config['command'])
      ->setAliases($this->buildAliasesFromConfig())
      ->setDefinition($this->buildDefinitionFromConfig())
      ->setDescription($this->config['description'])
      ->setHelp($this->buildHelpFromConfig());
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $args = $input->getArguments();
    $first = array_shift($args);

    // If the first argument is an alias, assign the next argument as the
    // command.
    if (strpos($first, '@') === 0) {
      $alias = $first;
      $command = array_shift($args);
    }
    // Otherwise, default the alias to '@self' and use the first argument as the
    // command.
    else {
      $alias = '@self';
      $command = $first;
    }

    $options = $input->getOptions();
    // Force the 'backend' option to TRUE.
    $options['backend'] = TRUE;

    $return = drush_invoke_process($alias, $command, array_values($args), $options, ['interactive' => TRUE]);

    if ($return['error_status'] > 0) {
      foreach ($return['error_log'] as $error_type => $errors) {
        $output->write($errors);
      }
      // Add a newline after so the shell returns on a new line.
      $output->writeln('');
    }
    else {
      $output->page(drush_backend_get_result());
    }
  }

  /**
   * Extract Drush command aliases from config array.
   *
   * @return array
   *   The command aliases.
   */
  protected function buildAliasesFromConfig() {
    return !empty($this->config['aliases']) ? $this->config['aliases'] : [];
  }

  /**
   * Build a command definition from Drush command configuration array.
   *
   * Currently, adds all non-hidden arguments and options, and makes a decent
   * effort to guess whether an option accepts a value or not. It isn't always
   * right :P
   *
   * @return array
   *   the command definition.
   */
  protected function buildDefinitionFromConfig() {
    $definitions = [];

    if (isset($this->config['arguments']) && !empty($this->config['arguments'])) {
      $required_args = $this->config['required-arguments'];

      if ($required_args === FALSE) {
        $required_args = 0;
      }
      elseif ($required_args === TRUE) {
        $required_args = count($this->config['arguments']);
      }

      foreach ($this->config['arguments'] as $name => $argument) {
        if (!is_array($argument)) {
          $argument = ['description' => $argument];
        }

        if (!empty($argument['hidden'])) {
          continue;
        }

        $input_type = ($required_args-- > 0) ? InputArgument::REQUIRED : InputArgument::OPTIONAL;

        $definitions[] = new InputArgument($name, $input_type, $argument['description'], NULL);
      }
    }

    // First create all global options.
    $options = $this->config['options'] + drush_get_global_options();

    // Add command specific options.
    $definitions = array_merge($definitions, $this->createInputOptionsFromConfig($options));

    return $definitions;
  }

  /**
   * Creates input definitions from command options.
   *
   * @param array $options_config
   *
   * @return \Symfony\Component\Console\Input\InputInterface[]
   */
  protected function createInputOptionsFromConfig(array $options_config) {
    $definitions = [];

    foreach ($options_config as $name => $option) {
      // Some commands will conflict.
      if (in_array($name, ['help', 'command'])) {
        continue;
      }

      if (!is_array($option)) {
        $option = ['description' => $option];
      }

      if (!empty($option['hidden'])) {
        continue;
      }

      // @todo: Figure out if there's a way to detect InputOption::VALUE_NONE
      // (i.e. flags) via the config array.
      if (isset($option['value']) && $option['value'] === 'required') {
        $input_type = InputOption::VALUE_REQUIRED;
      }
      else {
        $input_type = InputOption::VALUE_OPTIONAL;
      }

      $definitions[] = new InputOption($name, !empty($option['short-form']) ? $option['short-form'] : '', $input_type, $option['description']);
    }

    return $definitions;
  }

  /**
   * Build a command help from the Drush configuration array.
   *
   * Currently it's a word-wrapped description, plus any examples provided.
   *
   * @return string
   *   The help string.
   */
  protected function buildHelpFromConfig() {
    $help = wordwrap($this->config['description']);

    $examples = [];
    foreach ($this->config['examples'] as $ex => $def) {
      // Skip empty examples and things with obvious pipes...
      if (($ex === '') || (strpos($ex, '|') !== FALSE)) {
        continue;
      }

      $ex = preg_replace('/^drush\s+/', '', $ex);
      $examples[$ex] = $def;
    }

    if (!empty($examples)) {
      $help .= "\n\ne.g.";

      foreach ($examples as $ex => $def) {
        $help .= sprintf("\n<return>// %s</return>\n", wordwrap(OutputFormatter::escape($def), 75, "</return>\n<return>// "));
        $help .= sprintf("<return>>>> %s</return>\n", OutputFormatter::escape($ex));
      }
    }

    return $help;
  }

}
