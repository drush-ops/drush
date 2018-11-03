<?php

namespace Drupal\Core\Template\Loader;

/**
 * Loads string templates, also known as inline templates.
 *
 * This loader is intended to be used in a Twig loader chain and whitelists
 * string templates that begin with the following comment:
 * @code
 * {# inline_template_start #}
 * @endcode
 *
 * This class override ensures that the string loader behaves as expected in
 * the loader chain. If Twig's string loader is used as is, any string (even a
 * reference to a file-based Twig template) is treated as a valid template and
 * is rendered instead of a \Twig_Error_Loader exception being thrown.
 *
 * @see \Drupal\Core\Template\TwigEnvironment::renderInline()
 * @see \Drupal\Core\Render\Element\InlineTemplate
 * @see twig_render_template()
 */
class StringLoader implements \Twig_LoaderInterface, \Twig_ExistsLoaderInterface {

  /**
   * {@inheritdoc}
   */
  public function exists($name) {
    if (strpos($name, '{# inline_template_start #}') === 0) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSource($name) {
    return $name;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheKey($name) {
    return $name;
  }

  /**
   * {@inheritdoc}
   */
  public function isFresh($name, $time) {
    return TRUE;
  }

}
