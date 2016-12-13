<?php
namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;

/*
 * Common options providers. Use them by adding an annotation to your method.
 */
trait OptionsTrait {

  /**
   * @hook option @options_proc_build
   * @option ssh-options A string of extra options that will be passed to the ssh command (e.g. "-p 100")',
   * @option tty Create a tty (e.g. to run an interactive program).',
   * @option escaped Command string already escaped; do not add additional quoting.',
   */
  public function options_proc_build() {}

}


