<?php
/**
 * @file
 * Drush Command class.
 *
 * Original author: Justin Hileman
 *
 * DrushCommand is a PsySH proxy command which accepts a drush command config
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
 * Main DrushCommand class.
 */
class DrushCommand extends BaseCommand {

  /**
   * @var array
   */
  private $config;

  /**
   * @var string
   */
  private $category;

  /**
   * DrushCommand constructor.
   *
   * This accepts the drush command configuration array and does a pretty
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
    if (isset($this->category)) {
      return $this->category;
    }

    $category = $this->config['category'];
    $title = drush_command_invoke_all('drush_help', "meta:$category:title");

    if (!$title) {
      // If there is no title, then check to see if the
      // command file is stored in a folder with the same
      // name as some other command file (e.g. 'core') that
      // defines a title.
      $category = basename($this->config['path']);
      $title = drush_command_invoke_all('drush_help', "meta:$category:title");
    }

    return $this->category = empty($title) ? 'Other commands' : $title[0];
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
    // @todo support aliases
    $args = $input->getArguments();
    $command = array_shift($args);

    $return = drush_invoke_process('@self', $command, array_values($args), $input->getOptions());

    if ($return['error_status'] > 0) {
      foreach ($return['error_log'] as $error_type => $errors) {
        $output->write($errors);
      }
      // Add a newline after so the shell returns on a new line.
      $output->writeln('');
    }
    else {
      // @todo If the command is successful drush prints the output, can we stop
      // that and just write to the output here?
      //$output->page($return['output']);
    }
  }

  /**
   * Extract drush command aliases from config array.
   *
   * @return array
   *   The command aliases.
   */
  protected function buildAliasesFromConfig() {
    return !empty($this->config['aliases']) ? $this->config['aliases'] : [];
  }

  /**
   * Build a command definition from drush command configuration array.
   *
   * Currently, adds all non-hidden arguments and options, and makes a decent
   * effort to guess whether an option accepts a value or not. It isn't always
   * right :P
   *
   * @return array
   *   the command definition.
   */
  protected function buildDefinitionFromConfig() {
    $def = [];

    if (isset($this->config['arguments']) && !empty($this->config['arguments'])) {

      $required_args = $this->config['required-arguments'];
      if ($required_args === FALSE) {
        $required_args = 0;
      }
      elseif ($required_args === TRUE) {
        $required_args = count($this->config['arguments']);
      }

      foreach ($this->config['arguments'] as $name => $arg) {
        if (!is_array($arg)) {
          $arg = array('description' => $arg);
        }

        if (isset($arg['hidden']) && $arg['hidden']) {
          continue;
        }

        $req = ($required_args-- > 0) ? InputArgument::REQUIRED : InputArgument::OPTIONAL;

        $def[] = new InputArgument($name, $req, $arg['description'], NULL);
      }
    }

    if (isset($this->config['options']) && !empty($this->config['options'])) {
      foreach ($this->config['options'] as $name => $opt) {
        if (!is_array($opt)) {
          $opt = array('description' => $opt);
        }

        if (isset($opt['hidden']) && $opt['hidden']) {
          continue;
        }

        // TODO: figure out if there's a way to detect
        // InputOption::VALUE_NONE (i.e. flags) via the config array.
        if (isset($opt['value']) && $opt['value'] !== 'optional') {
          $req = InputOption::VALUE_REQUIRED;
        }
        else {
          $req = InputOption::VALUE_OPTIONAL;
        }

        $def[] = new InputOption($name, '', $req, $opt['description']);
      }
    }

    return $def;
  }

  /**
   * Build a command help from the drush configuration array.
   *
   * Currently it's a word-wrapped description, plus any examples provided.
   *
   * @return string
   *   The help string.
   */
  private function buildHelpFromConfig() {
    $help = wordwrap($this->config['description']);

    $examples = array();
    foreach ($this->config['examples'] as $ex => $def) {
      // Skip empty examples and things with obvious pipes...
      if ($ex === '' || strpos($ex, '|') !== FALSE) {
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
