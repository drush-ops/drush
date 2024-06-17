<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

class HtaccessCommands extends DrushCommands
{
    const REGENERATE_HTACCESS = 'htaccess:regenerate';

    /**
     * Regenerate the .htaccess files managed by Drupal.
     *
     * @command htaccess:regenerate
     * @aliases htaccess-regenerate
     * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
     */
    #[CLI\Command(name: self::REGENERATE_HTACCESS, aliases: ['htaccess-regenerate'])]
    public function regenerateHtaccess()
    {
        // Use the htaccess_writer service to regenerate the .htaccess files
        \Drupal::service('file.htaccess_writer')->ensure();

        $this->logger()->success(dt('.htaccess files have been successfully regenerated.'));
    }
}
