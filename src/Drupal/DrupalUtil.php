<?php

declare(strict_types=1);

namespace Drush\Drupal;

use Drupal\Core\Mail\MailFormatHelper;

class DrupalUtil
{
    /**
     * Output a Drupal render array, object or string as plain text.
     *
     * @param string|array $data
     *   Data to render.
     *
     *   The plain-text representation of the input.
     */
    public static function drushRender(string|array $data): string
    {
        if (is_array($data)) {
            $data = \Drupal::service('renderer')->renderRoot($data);
        }
        return MailFormatHelper::htmlToText((string) $data);
    }
}
