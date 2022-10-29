<?php

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
    public static function drushRender($data): string
    {
        if (is_array($data)) {
            $data = \Drupal::service('renderer')->renderRoot($data);
        }

        $data = MailFormatHelper::htmlToText((string) $data);
        return $data;
    }
}
