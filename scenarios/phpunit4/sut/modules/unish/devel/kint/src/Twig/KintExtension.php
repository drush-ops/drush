<?php

namespace Drupal\kint\Twig;

/**
 * Provides the Kint debugging function within Twig templates.
 */
class KintExtension extends \Twig_Extension {

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'kint';
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return array(
      new \Twig_SimpleFunction('kint', array($this, 'kint'), array(
        'is_safe' => array('html'),
        'needs_environment' => TRUE,
        'needs_context' => TRUE,
        'is_variadic' => TRUE,
      )),
    );
  }

  /**
   * Provides Kint function to Twig templates.
   *
   * Handles 0, 1, or multiple arguments.
   *
   * Code derived from https://github.com/barelon/CgKintBundle.
   *
   * @param \Twig_Environment $env
   *   The twig environment instance.
   * @param array $context
   *   An array of parameters passed to the template.
   * @param array $args
   *   An array of parameters passed the function.
   *
   * @return string
   *   String representation of the input variables.
   */
  public function kint(\Twig_Environment $env, array $context, array $args = []) {
    // Don't do anything unless twig_debug is enabled. This reads from the Twig
    // environment, not Drupal Settings, so a container rebuild is necessary
    // when toggling twig_debug on and off. We can consider injecting Settings.
    if (!$env->isDebug()) {
      return;
    }
    kint_require();
    // Don't display where Kint was called from.
    // @todo Can we add information about which template Kint was called from?
    \Kint::$displayCalledFrom = FALSE;

    // No arguments passed to kint(), display full Twig context.
    if (empty($args)) {
      $kint_variable = array();
      foreach ($context as $key => $value) {
        if (!$value instanceof \Twig_Template) {
          $kint_variable[$key] = $value;
        }
      }

      $result = @\Kint::dump($kint_variable);
      $output = str_replace('$kint_variable', 'Twig context', $result);
    }
    else {
      // Try to get the names of variables from the Twig template.
      $parameters = $this->getTwigFunctionParameters();

      // If there is only one argument, pass to Kint without too much hassle.
      if (count($args) == 1) {
        $kint_variable = reset($args);
        $variable_name = reset($parameters);
        $result = @\Kint::dump($kint_variable);
        // Replace $kint_variable with the name of the variable in the Twig
        // template.
        $output = str_replace('$kint_variable', $variable_name, $result);
      }
      else {
        $kint_args = [];
        // Build an array of variable to pass to Kint.
        // @todo Can we just call_user_func_array while still retaining the
        //   variable names?
        foreach ($args as $index => $arg) {
          // Prepend a unique index to allow debugging the same variable more
          // than once in the same Kint dump.
          $name = !empty($parameters[$index]) ? $parameters[$index] : $index;
          $kint_args['_index_' . $index . '_' . $name] = $arg;
        }

        $result = @\Kint::dump($kint_args);
        // Display a comma separated list of the variables contained in this group.
        $output = str_replace('$kint_args', implode(', ', $parameters), $result);
        // Remove unique indexes from output.
        $output = preg_replace('/_index_([0-9]+)_/', '', $output);
      }
    }

    return $output;
  }

  /**
   * Gets the twig function parameters for the current invocation.
   *
   * @return array
   *   The detected twig function parameters.
   */
  protected function getTwigFunctionParameters() {
    $callee = NULL;
    $template = NULL;

    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT);

    foreach ($backtrace as $index => $trace) {
      if (isset($trace['object']) && $trace['object'] instanceof \Twig_Template && 'Twig_Template' !== get_class($trace['object'])) {
        $template = $trace['object'];
        $callee = $backtrace[$index - 1];
        break;
      }
    }

    $parameters = [];

    /** @var \Twig_Template $template */
    if (NULL !== $template && NULL !== $callee) {
      $line_number = $callee['line'];
      $debug_infos = $template->getDebugInfo();

      if (isset($debug_infos[$line_number])) {
        $source_line = $debug_infos[$line_number];
        $source_file_name = $template->getTemplateName();

        if (is_readable($source_file_name)) {
          $source = file($source_file_name, FILE_IGNORE_NEW_LINES);
          $line = $source[$source_line - 1];

          preg_match('/kint\((.+)\)/', $line, $matches);
          if (isset($matches[1])) {
            $parameters = array_map('trim', explode(',', $matches[1]));
          }
        }
      }
    }

    return $parameters;
  }

}
