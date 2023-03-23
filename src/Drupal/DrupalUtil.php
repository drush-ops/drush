<?php

declare(strict_types=1);

namespace Drush\Drupal;

use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Render\Markup;

class DrupalUtil
{
    /**
     * Output a Drupal render array, object or string as plain text.
     *
     * @param string|array|Markup $data
     *   Data to render.
     *
     *   The plain-text representation of the input.
     */
    public static function drushRender($data): string
    {
        if (is_array($data)) {
            $data = \Drupal::service('renderer')->renderRoot($data);
        }
        return MailFormatHelper::htmlToText((string) $data);
    }
}
