<?php

namespace Drupal\user\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Plugin to replace !tokens with [tokens].
 *
 * @MigrateProcessPlugin(
 *   id = "convert_tokens",
 *   handle_multiples = TRUE
 * )
 */
class ConvertTokens extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $tokens = [
      '!site' => '[site:name]',
      '!username' => '[user:name]',
      '!mailto' => '[user:mail]',
      '!login_uri' => '[site:login-url]',
      '!uri_brief' => '[site:url-brief]',
      '!edit_uri' => '[user:edit-url]',
      '!login_url' => '[user:one-time-login-url]',
      '!uri' => '[site:url]',
      '!date' => '[date:medium]',
      '!password' => '',
    ];

    // Given that our source is a database column that could hold a NULL
    // value, sometimes that filters down to here. str_replace() cannot
    // handle NULLs as the subject, so we reset to an empty string.
    if (is_null($value)) {
      $value = '';
    }

    if (is_string($value)) {
      return str_replace(array_keys($tokens), $tokens, $value);
    }
    else {
      throw new MigrateException('Value must be a string.');
    }
  }

}
